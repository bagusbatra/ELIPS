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
    'admin' => ['admin/dashboard.php', 'admin/users.php', 'admin/attendance-recap.php', 'admin/shifts.php', 'admin/course-publish.php?id=1', 'learning.php',
                'employee/operasional/siswa.php', 'employee/keuangan/dashboard.php'],
    'instruktur' => ['instructor/dashboard.php', 'instructor/manage-course.php', 'instructor/angkatan.php', 'instructor/angkatan-detail.php?id=1', 'instructor/kurikulum.php', 'instructor/kurikulum-draf.php?course=1', 'instructor/penilaian.php', 'instructor/penilaian-angkatan.php?id=1', 'instructor/penilaian-tugas.php?assignment=1', 'instructor/penilaian-akhir.php?id=1', 'instructor/penilaian-rapor.php?id=1&student=1', 'learning.php'],
    'peserta' => ['student/dashboard.php', 'student/courses.php', 'learning.php'],
    'karyawan-marketing' => ['employee/shared/dashboard.php', 'employee/shared/attendance.php', 'employee/marketing/tasks.php', 'employee/marketing/campaigns.php'],
    'karyawan-operasional' => ['employee/shared/dashboard.php', 'employee/operasional/siswa.php', 'employee/operasional/jadwal.php', 'employee/operasional/kursus-publikasi.php?id=1'],
    'karyawan-keuangan' => ['employee/shared/dashboard.php', 'employee/keuangan/dashboard.php'],
];
$forbidden = [
    'admin' => ['instructor/dashboard.php', 'student/dashboard.php', 'employee/shared/dashboard.php', 'employee/marketing/tasks.php'],
    'instruktur' => ['admin/dashboard.php', 'admin/course-publish.php?id=1', 'student/dashboard.php', 'employee/shared/attendance.php'],
    'peserta' => ['admin/dashboard.php', 'admin/users.php', 'instructor/dashboard.php', 'employee/shared/dashboard.php'],
    'karyawan-marketing' => ['admin/dashboard.php', 'employee/operasional/kursus-publikasi.php?id=1', 'student/dashboard.php', 'employee/operasional/siswa.php', 'employee/keuangan/dashboard.php', 'learning.php'],
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
$noT = $ops->api('course.create', ['title' => 'Uji Tanpa Tentor', 'category' => 'Pelatihan', 'price' => 100000, 'meetings' => 8, 'location' => 'Candi']);
t('kursus dapat dibuat tanpa tentor', !empty($noT['ok']) && array_key_exists('instructorId', $noT['data'] ?? []) && $noT['data']['instructorId'] === null, json_encode($noT['message'] ?? ''));
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


// ============================================================
// Akun tentor: dibuat admin (nama saja) -> link -> biodata + berkas -> sandi
// ============================================================
echo "\n[Akun tentor]\n";
$pdf = static fn(string $extra = ''): string => 'data:application/pdf;base64,' . base64_encode("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" . $extra);
$jpg = make_jpeg(91);
$tok = static fn(string $link): string => preg_match('/token=([a-f0-9]{64})/', $link, $m) ? $m[1] : '';

t('karyawan tidak boleh menambah tentor', empty($keu->api('tutor.create', ['name' => 'X'])['ok']));
t('nama tentor wajib diisi', empty($adm->api('tutor.create', ['name' => '  '])['ok']));
$res = $adm->api('tutor.create', ['name' => 'Tentor Uji Aktivasi']);
t('admin menambah tentor hanya dengan nama', !empty($res['ok']), $res['message'] ?? '');
$tid = (int) ($res['data']['id'] ?? 0);
t('akun tentor berstatus menunggu dan role instruktur', ($res['data']['status'] ?? '') === 'menunggu' && ($res['data']['role'] ?? '') === 'instruktur');
$placeholder = (string) ($res['data']['email'] ?? '');
$x = new Client($base);
$r = $x->api('auth.login', ['identifier' => $placeholder, 'email' => $placeholder, 'password' => 'apa-saja']);
t('akun tentor belum bisa dipakai masuk', empty($r['ok']));

t('karyawan tidak boleh membuat link tentor', empty($keu->api('tutor.sendActivation', ['userId' => $tid])['ok']));
$res = $adm->api('tutor.sendActivation', ['userId' => $tid]);
t('admin membuat link aktivasi tentor', !empty($res['ok']) && strpos($res['data']['link'] ?? '', 'activate-tentor.php?token=') !== false, $res['message'] ?? '');
$token = $tok((string) ($res['data']['link'] ?? ''));
t('link berisi pesan WhatsApp', strpos($res['data']['waUrl'] ?? '', 'https://wa.me/?text=') === 0);
$old = $token;
$res = $adm->api('tutor.sendActivation', ['userId' => $tid]);
$token = $tok((string) ($res['data']['link'] ?? ''));
t('kirim ulang mencabut link lama', empty((new Client($base))->api('tutor.activationInfo', ['token' => $old])['ok']) && !empty((new Client($base))->api('tutor.activationInfo', ['token' => $token])['ok']));
t('token ngawur ditolak', empty((new Client($base))->api('tutor.activationInfo', ['token' => 'abc'])['ok']));

$pub = new Client($base);
$info = $pub->api('tutor.activationInfo', ['token' => $token]);
t('info aktivasi memuat nama tentor', ($info['data']['name'] ?? '') === 'Tentor Uji Aktivasi');

$profile = ['fullName' => 'Tentor Uji Aktivasi, S.Pd.', 'birthPlace' => 'Bandung', 'birthDate' => '1990-05-17',
    'address' => 'Jl. Merdeka No. 1, Bandung', 'phone' => '081355500123', 'email' => 'tentor.uji@example.com'];
$strong = 'Rahasia#2026';

$res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => $profile, 'password' => $strong, 'passwordConfirm' => $strong]);
t('aktivasi tanpa berkas ditolak', empty($res['ok']) && stripos($res['message'] ?? '', 'CV') !== false, $res['message'] ?? '');

$up = static fn(array $o) => $pub->api('tutor.document.upload', $o + ['token' => $token]);
t('jenis berkas tidak valid ditolak', empty($up(['kind' => 'foto', 'fileName' => 'a.pdf', 'dataUrl' => $pdf()])['ok']));
t('berkas non PDF/JPG/PNG ditolak', empty($up(['kind' => 'cv', 'fileName' => 'a.html', 'dataUrl' => 'data:text/html;base64,' . base64_encode('<script>1</script>')])['ok']));
t('PDF palsu ditolak', empty($up(['kind' => 'cv', 'fileName' => 'a.pdf', 'dataUrl' => 'data:application/pdf;base64,' . base64_encode('bukan pdf')])['ok']));
t('gambar palsu ditolak', empty($up(['kind' => 'ktp', 'fileName' => 'a.jpg', 'dataUrl' => 'data:image/jpeg;base64,' . base64_encode('bukan gambar')])['ok']));
t('berkas > 3 MB ditolak', empty($up(['kind' => 'cv', 'fileName' => 'besar.pdf', 'dataUrl' => $pdf(str_repeat('A', 3 * 1024 * 1024))])['ok']));
t('upload dengan token salah ditolak', empty((new Client($base))->api('tutor.document.upload', ['token' => str_repeat('a', 64), 'kind' => 'cv', 'fileName' => 'a.pdf', 'dataUrl' => $pdf()])['ok']));

t('unggah CV', !empty($up(['kind' => 'cv', 'fileName' => 'cv.pdf', 'dataUrl' => $pdf()])['ok']));
$r = $up(['kind' => 'cv', 'fileName' => 'cv-baru.pdf', 'dataUrl' => $pdf('v2')]);
$cvCount = count(array_filter($r['data']['documents'] ?? [], fn($d) => $d['kind'] === 'cv'));
t('unggah CV lagi menggantikan yang lama', !empty($r['ok']) && $cvCount === 1);
t('unggah KTP (JPG)', !empty($up(['kind' => 'ktp', 'fileName' => 'ktp.jpg', 'dataUrl' => $jpg])['ok']));

$res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => $profile, 'password' => $strong, 'passwordConfirm' => $strong]);
t('aktivasi tanpa ijazah ditolak', empty($res['ok']) && stripos($res['message'] ?? '', 'Ijazah') !== false);
t('unggah ijazah', !empty($up(['kind' => 'ijazah', 'fileName' => 'ijazah.pdf', 'dataUrl' => $pdf()])['ok']));
$res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => $profile, 'password' => $strong, 'passwordConfirm' => $strong]);
t('aktivasi tanpa sertifikat ditolak', empty($res['ok']) && stripos($res['message'] ?? '', 'sertifikat') !== false);
t('sertifikat wajib diberi nama', empty($up(['kind' => 'sertifikat', 'fileName' => 's.pdf', 'dataUrl' => $pdf()])['ok']));
$r = $up(['kind' => 'sertifikat', 'label' => 'Sertifikat TOEFL', 'fileName' => 'toefl.pdf', 'dataUrl' => $pdf()]);
t('unggah sertifikat pertama', !empty($r['ok']));
$r = $up(['kind' => 'sertifikat', 'label' => 'Sertifikat Pelatihan', 'fileName' => 'p.jpg', 'dataUrl' => $jpg]);
$certs = array_values(array_filter($r['data']['documents'] ?? [], fn($d) => $d['kind'] === 'sertifikat'));
t('sertifikat bisa ditambah lagi', count($certs) === 2);
$res = $pub->api('tutor.document.remove', ['token' => $token, 'id' => $certs[1]['id'] ?? 0]);
t('sertifikat bisa dihapus', !empty($res['ok']) && count(array_filter($res['data']['documents'], fn($d) => $d['kind'] === 'sertifikat')) === 1);

foreach ([['fullName', ''], ['birthPlace', ''], ['birthDate', ''], ['address', ''], ['phone', ''], ['email', ''], ['email', 'bukan-email'], ['birthDate', '2999-01-01'], ['phone', '123']] as [$k, $v]) {
    $res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => array_merge($profile, [$k => $v]), 'password' => $strong, 'passwordConfirm' => $strong]);
    t("aktivasi ditolak bila $k = '" . $v . "'", empty($res['ok']), $res['message'] ?? '');
}
$res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => $profile, 'password' => 'lemah', 'passwordConfirm' => 'lemah']);
t('sandi lemah ditolak', empty($res['ok']));
$res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => $profile, 'password' => $strong, 'passwordConfirm' => 'Beda#2026x']);
t('konfirmasi sandi harus sama', empty($res['ok']));
$res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => array_merge($profile, ['email' => 'admin@example.com']), 'password' => $strong, 'passwordConfirm' => $strong]);
t('email yang dipakai akun lain ditolak', empty($res['ok']));

$res = $pub->api('tutor.activationComplete', ['token' => $token, 'profile' => $profile, 'password' => $strong, 'passwordConfirm' => $strong]);
t('tentor menyelesaikan aktivasi', !empty($res['ok']), $res['message'] ?? '');
t('diarahkan ke dashboard tentor', ($res['data']['redirect'] ?? '') === 'instructor/dashboard.php');
$row = $pdo->query("SELECT * FROM users WHERE id = $tid")->fetch();
t('akun aktif dengan email & telepon baru', $row['status'] === 'aktif' && $row['email'] === 'tentor.uji@example.com' && $row['name'] === 'Tentor Uji Aktivasi, S.Pd.' && $row['role'] === 'instruktur');
t('biodata tentor tersimpan', (int) $pdo->query("SELECT COUNT(*) FROM tutor_profiles WHERE user_id = $tid AND birth_place = 'Bandung' AND completed_at IS NOT NULL")->fetchColumn() === 1);
$page = $pub->get('/instructor/dashboard.php');
t('tentor langsung masuk dashboard tentor', $page['code'] === 200, (string) $page['code']);
t('link tidak bisa dipakai dua kali', empty($pub->api('tutor.activationComplete', ['token' => $token, 'profile' => $profile, 'password' => $strong, 'passwordConfirm' => $strong])['ok']));
t('link sekali pakai tak bisa unggah lagi', empty($up(['kind' => 'cv', 'fileName' => 'a.pdf', 'dataUrl' => $pdf()])['ok']));

$fresh = new Client($base);
$r = $fresh->api('auth.login', ['identifier' => 'tentor.uji@example.com', 'email' => 'tentor.uji@example.com', 'password' => $strong]);
t('tentor bisa login dengan email aktif', !empty($r['ok']), $r['message'] ?? '');

$res = $adm->api('tutor.sendActivation', ['userId' => $tid]);
t('link untuk tentor yang sudah aktif ditolak', empty($res['ok']));

// Admin melihat berkas
$prof = $adm->api('tutor.profile', ['userId' => $tid]);
t('admin melihat biodata & berkas tentor', !empty($prof['ok']) && ($prof['data']['profile']['birthPlace'] ?? '') === 'Bandung' && count($prof['data']['documents'] ?? []) === 4, json_encode($prof['message'] ?? ''));
t('karyawan tidak boleh melihat profil tentor', empty($keu->api('tutor.profile', ['userId' => $tid])['ok']));
$docId = (int) ($prof['data']['documents'][0]['id'] ?? 0);
$f = $adm->get('/api/index.php?action=tutor.document&id=' . $docId);
t('admin dapat membuka berkas', $f['code'] === 200 && strncmp($f['body'], '%PDF-', 5) === 0, (string) $f['code']);
$f = $fresh->get('/api/index.php?action=tutor.document&id=' . $docId);
t('tentor pemilik dapat membuka berkasnya', $f['code'] === 200);
$f = $keu->get('/api/index.php?action=tutor.document&id=' . $docId);
t('pengguna lain tidak dapat membuka berkas tentor', $f['code'] === 403, (string) $f['code']);
$f = (new Client($base))->get('/api/index.php?action=tutor.document&id=' . $docId);
t('tanpa login tidak dapat membuka berkas tentor', $f['code'] === 403, (string) $f['code']);
$f = $adm->get('/storage/tutor-docs/');
t('folder berkas tentor tidak bisa dibuka langsung', $f['code'] !== 200 || strpos($f['body'], '%PDF') === false);

$pdo->exec("DELETE FROM users WHERE id = $tid");
foreach (glob($root . '/storage/tutor-docs/*') ?: [] as $fl) { if (basename($fl) !== '.htaccess') { @unlink($fl); } }

// Bersihkan data uji bagian ini
$pdo->exec("DELETE FROM classes WHERE code IN ('UJI-ACT-01','UJI-ACT-02','UJI-ACT-03')");
$pdo->exec("UPDATE courses SET price = 0, discount_price = 0 WHERE id IN (1, 2)");
$pdo->exec("UPDATE courses SET status = 'draft' WHERE id = 2");
foreach (glob($root . '/storage/payment-proofs/*') ?: [] as $f) { if (basename($f) !== '.htaccess') { @unlink($f); } }


// ============================================================
echo "\n== Portal tentor (kurikulum, penilaian tugas, penilaian akhir)\n";
$tpTentor = (int) $pdo->query("SELECT id FROM users WHERE email = 'ahmad@example.com'")->fetchColumn();
$pdo->exec("INSERT INTO courses (title, category, description, instructor_id, status) VALUES ('Kursus Uji Portal', 'Uji', 'Uji', $tpTentor, 'published')");
$tpCourse = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO curriculum_versions (course_id, code, name, description, status, notes) VALUES ($tpCourse, 'UJI-V1', 'Versi uji', '', 'published', '')");
$tpVer = (int) $pdo->lastInsertId();
$tpModules = [];
foreach ([['Modul Word', 1], ['Modul Excel', 2]] as [$mt, $mo]) {
    $pdo->prepare("INSERT INTO modules (course_id, curriculum_version_id, title, description, sort_order) VALUES (?, ?, ?, '', ?)")->execute([$tpCourse, $tpVer, $mt, $mo]);
    $tpModules[] = (int) $pdo->lastInsertId();
}
$tpLessons = [];
foreach ([[0, 'Format teks', 1], [0, 'Sisipkan gambar', 2], [1, 'Rumus dasar', 1], [1, 'Fungsi IF', 2]] as [$mi, $lt, $lo]) {
    $pdo->prepare("INSERT INTO lessons (module_id, course_id, curriculum_version_id, title, content, sort_order) VALUES (?, ?, ?, ?, '', ?)")->execute([$tpModules[$mi], $tpCourse, $tpVer, $lt, $lo]);
    $tpLessons[] = (int) $pdo->lastInsertId();
}
$pdo->prepare("INSERT INTO classes (code, name, course_id, curriculum_version_id, instructor_id, period_start, period_end, quota, status, notes)
               VALUES ('UJI-TP-01', 'Uji Portal 1', ?, ?, ?, '2026-09-01', '2026-10-30', 10, 'ongoing', '')")->execute([$tpCourse, $tpVer, $tpTentor]);
$tpClass = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO classes (code, name, course_id, curriculum_version_id, instructor_id, period_start, period_end, quota, status, notes)
               VALUES ('UJI-TP-02', 'Uji Portal 2', ?, ?, ?, '2027-01-12', '2027-03-12', 10, 'open', '')")->execute([$tpCourse, $tpVer, $tpTentor]);
$tpClass2 = (int) $pdo->lastInsertId();
for ($n = 1; $n <= 4; $n++) {
    $st = $n <= 2 ? 'confirmed' : 'scheduled';
    $pdo->prepare("INSERT INTO class_sessions (class_id, session_number, title, session_date, status, planned_jp, notes) VALUES (?, ?, ?, ?, ?, 2, '')")
        ->execute([$tpClass, $n, "Sesi $n", date('Y-m-d', strtotime("2026-09-0$n")), $st]);
}
$tpEnroll = [];
foreach (['Siswa Uji A', 'Siswa Uji B'] as $i => $nm) {
    $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, status, created_at) VALUES (?, ?, '', 'x', 'peserta', 'aktif', CURDATE())")->execute([$nm, "tp-siswa$i@example.com"]);
    $sid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, class_id, status) VALUES (?, ?, ?, 'active')")->execute([$sid, $tpCourse, $tpClass]);
    $tpEnroll[] = ['student' => $sid, 'enrollment' => (int) $pdo->lastInsertId()];
}

$tp = $clients['instruktur'];
$anonTp = new Client($base);
t('tp.dashboard ditolak tanpa login', empty($anonTp->api('tp.dashboard')['ok']));
t('tp.dashboard ditolak untuk admin', empty($clients['admin']->api('tp.dashboard')['ok']));
t('tp.dashboard ditolak untuk peserta', empty($clients['peserta']->api('tp.classes')['ok']));

$res = $tp->api('tp.dashboard');
t('tp.dashboard ok tanpa snapshot', !empty($res['ok']) && !isset($res['snapshot']), $res['message'] ?? '');
t('dashboard memuat angkatan uji', in_array('UJI-TP-01', array_column($res['data']['classes'] ?? [], 'code'), true));

$res = $tp->api('tp.class.detail', ['classId' => $tpClass]);
t('detail angkatan: 4 sesi, 2 terkunci', count($res['data']['sessions'] ?? []) === 4 && count(array_filter($res['data']['sessions'], fn($x) => $x['locked'])) === 2, json_encode($res['message'] ?? ''));
t('detail angkatan: 2 siswa', count($res['data']['students'] ?? []) === 2);
t('angkatan tentor lain ditolak', empty($tp->api('tp.class.detail', ['classId' => 999999])['ok']));

$res = $tp->api('tp.curriculum.courses');
t('kartu kurikulum memuat kursus uji', in_array($tpCourse, array_column($res['data']['courses'] ?? [], 'courseId'), true));
$res = $tp->api('tp.curriculum.get', ['courseId' => $tpCourse]);
t('kurikulum: 2 modul, sesi 1-2 terkunci', count($res['data']['modules'] ?? []) === 2 && $res['data']['modules'][0]['lessons'][0]['locked'] && !$res['data']['modules'][1]['lessons'][0]['locked'], json_encode($res['message'] ?? ''));

$task = ['title' => 'Latihan rumus', 'instructions' => 'Kerjakan', 'dueDays' => 3, 'format' => 'file_upload', 'maxScore' => 100, 'weight' => 20];
$res = $tp->api('tp.curriculum.save', ['courseId' => $tpCourse, 'versionId' => $tpVer, 'lessons' => [[
    'id' => $tpLessons[2], 'objective' => 'Memahami rumus', 'durationJp' => 3,
    'materials' => [['title' => 'Slide rumus', 'kind' => 'dokumen', 'content' => 'slide.pdf']], 'tasks' => [$task],
]]]);
t('simpan isi sesi 3 berhasil', !empty($res['ok']) && count($res['data']['changed'] ?? []) === 1, $res['message'] ?? '');
$taskId = (int) ($res['data']['lessons'][0]['tasks'][0]['id'] ?? 0);
t('template penugasan tersimpan dengan id', $taskId > 0);
$cnt = (int) $pdo->query("SELECT COUNT(*) FROM assignments WHERE template_task_id = $taskId")->fetchColumn();
t('penugasan disalin ke 2 angkatan (sesi belum terkunci)', $cnt === 2, (string) $cnt);
$due = (string) $pdo->query("SELECT due_at FROM assignments WHERE template_task_id = $taskId AND class_id = $tpClass")->fetchColumn();
t('tenggat = tanggal sesi + 3 hari', strpos($due, '2026-09-06') === 0, $due);

$res = $tp->api('tp.curriculum.save', ['courseId' => $tpCourse, 'versionId' => $tpVer, 'lessons' => [[
    'id' => $tpLessons[2], 'objective' => 'Memahami rumus', 'durationJp' => 3,
    'materials' => [['id' => (int) $pdo->query("SELECT id FROM lesson_materials WHERE lesson_id = {$tpLessons[2]}")->fetchColumn(), 'title' => 'Slide rumus', 'kind' => 'dokumen', 'content' => 'slide.pdf']],
    'tasks' => [array_merge($task, ['id' => $taskId])],
]]]);
t('simpan tanpa perubahan tidak mencatat riwayat', !empty($res['ok']) && count($res['data']['changed'] ?? [1]) === 0, json_encode($res['data']['changed'] ?? null));

$res = $tp->api('tp.curriculum.save', ['courseId' => $tpCourse, 'versionId' => $tpVer, 'lessons' => [['id' => $tpLessons[0], 'objective' => 'ubah', 'materials' => [], 'tasks' => []]]]);
t('sesi terkonfirmasi tidak bisa diubah', empty($res['ok']) && strpos($res['message'] ?? '', 'terkunci') !== false, $res['message'] ?? '');
$res = $tp->api('tp.curriculum.save', ['courseId' => $tpCourse, 'versionId' => $tpVer, 'lessons' => [['id' => $tpLessons[3], 'objective' => '', 'materials' => [], 'tasks' => [['title' => '  ']]]]]);
t('penugasan tanpa judul ditolak', empty($res['ok']));
$res = $tp->api('tp.curriculum.save', ['courseId' => $tpCourse, 'versionId' => $tpVer, 'lessons' => [['id' => $tpLessons[3], 'objective' => '', 'durationJp' => 200, 'materials' => [], 'tasks' => []]]]);
t('durasi di luar batas ditolak', empty($res['ok']));
$logs = (int) $pdo->query("SELECT COUNT(*) FROM curriculum_change_log WHERE course_id = $tpCourse")->fetchColumn();
t('riwayat perubahan tercatat', $logs === 1, (string) $logs);

// Penilaian tugas
$assignId = (int) $pdo->query("SELECT id FROM assignments WHERE template_task_id = $taskId AND class_id = $tpClass")->fetchColumn();
foreach ($tpEnroll as $i => $e) {
    $pdo->prepare("INSERT INTO submissions (assignment_id, enrollment_id, student_id, status, text_answer, feedback) VALUES (?, ?, ?, 'submitted', 'jawaban', '')")
        ->execute([$assignId, $e['enrollment'], $e['student']]);
}
$res = $tp->api('tp.grading.submissions', ['assignmentId' => $assignId]);
t('daftar pengumpulan: 2 siswa perlu dinilai', count(array_filter($res['data']['students'] ?? [], fn($x) => $x['state'] === 'perlu')) === 2, json_encode($res['message'] ?? ''));
$sub1 = (int) ($res['data']['students'][0]['submissionId'] ?? 0);
t('nilai di atas maksimal ditolak', empty($tp->api('tp.grading.score', ['submissionId' => $sub1, 'score' => 150])['ok']));
t('nilai kosong ditolak', empty($tp->api('tp.grading.score', ['submissionId' => $sub1, 'score' => ''])['ok']));
t('admin tidak bisa memberi nilai lewat portal tentor', empty($clients['admin']->api('tp.grading.score', ['submissionId' => $sub1, 'score' => 80])['ok']));
$res = $tp->api('tp.grading.score', ['submissionId' => $sub1, 'score' => 88.5]);
t('nilai tugas tersimpan', !empty($res['ok']) && $res['data']['score'] === 88.5, $res['message'] ?? '');
$st = $pdo->query("SELECT status, score FROM submissions WHERE id = $sub1")->fetch();
t('status pengumpulan menjadi reviewed', $st['status'] === 'reviewed' && (float) $st['score'] === 88.5);
$res = $tp->api('tp.grading.classes');
$row = array_values(array_filter($res['data']['classes'] ?? [], fn($x) => $x['id'] === $tpClass))[0] ?? null;
t('kartu penilaian: 1 perlu dinilai, akhir terkunci', $row && $row['pending'] === 1 && $row['finalState'] === 'locked', json_encode($row));

// Penilaian akhir
$e1 = $tpEnroll[0]['enrollment'];
$e2 = $tpEnroll[1]['enrollment'];
$res = $tp->api('tp.final.list', ['classId' => $tpClass]);
t('penilaian akhir: daftar siswa tampil walau materi belum dikonfirmasi', !empty($res['ok']) && count($res['data']['students'] ?? []) === 2, $res['message'] ?? '');
t('penilaian akhir: siswa belum siap (materi 0 dari N)', ($res['data']['students'][0]['materi']['ready'] ?? true) === false && ($res['data']['students'][0]['materi']['total'] ?? 0) > 0 && ($res['data']['gate']['studentsReady'] ?? -1) === 0, json_encode($res['data']['gate'] ?? null));
t('penilaian akhir: progres kehadiran & tugas per siswa', isset($res['data']['students'][0]['attendance']['present'], $res['data']['students'][0]['tasks']['total']));
$resGet = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e1]);
t('rapor siswa bisa dilihat saat materi belum dikonfirmasi, tetapi tidak bisa disimpan', !empty($resGet['ok']) && ($resGet['data']['gate']['canEdit'] ?? true) === false && empty($tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e1, 'structure' => $resGet['data']['structure']])['ok']));
t('kirim nilai akhir ditolak saat materi belum dikonfirmasi', empty($tp->api('tp.final.send', ['classId' => $tpClass])['ok']));
$tpVersion = (int) $pdo->query("SELECT curriculum_version_id FROM classes WHERE id = $tpClass")->fetchColumn();
$pdo->exec("INSERT IGNORE INTO class_lesson_progress (class_id, enrollment_id, student_id, lesson_id, completed_by)
            SELECT $tpClass, e.id, e.student_id, l.id, 1 FROM enrollments e JOIN lessons l ON l.curriculum_version_id = $tpVersion WHERE e.class_id = $tpClass AND e.id = $e1");
$res = $tp->api('tp.final.list', ['classId' => $tpClass]);
$byE = [];
foreach ($res['data']['students'] ?? [] as $stu) { $byE[$stu['enrollmentId']] = $stu; }
t('materi siswa 1 terkonfirmasi semua: siap dinilai, siswa 2 belum', ($byE[$e1]['materi']['ready'] ?? false) === true && ($byE[$e2]['materi']['ready'] ?? true) === false && ($res['data']['gate']['studentsReady'] ?? 0) === 1, json_encode($res['data']['gate'] ?? null));
t('kartu angkatan: akhir masih terkunci sebelum admin menutup pembelajaran', (array_values(array_filter($tp->api('tp.grading.classes')['data']['classes'] ?? [], fn($x) => $x['id'] === $tpClass))[0]['finalState'] ?? '') === 'locked');
$resGate = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e1]);
t('materi lengkap tetapi pembelajaran belum ditutup: tidak bisa diisi', ($resGate['data']['gate']['canEdit'] ?? true) === false && strpos($resGate['data']['gate']['reason'] ?? '', 'menutup pembelajaran') !== false, json_encode($resGate['data']['gate'] ?? null));
t('simpan nilai akhir ditolak sebelum pembelajaran ditutup', empty($tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e1, 'structure' => $resGate['data']['structure']])['ok']));
$pdo->exec("UPDATE classes SET status = 'completed', completed_at = UTC_TIMESTAMP() WHERE id = $tpClass");
t('kartu angkatan: akhir terbuka setelah admin menutup & ada siswa siap', (array_values(array_filter($tp->api('tp.grading.classes')['data']['classes'] ?? [], fn($x) => $x['id'] === $tpClass))[0]['finalState'] ?? '') === 'open');
$resGate = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e1]);
t('setelah ditutup, siswa dengan materi lengkap bisa diisi', ($resGate['data']['gate']['canEdit'] ?? false) === true);
$resGate2 = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e2]);
t('setelah ditutup, siswa dengan materi belum lengkap tetap terkunci', ($resGate2['data']['gate']['canEdit'] ?? true) === false && strpos($resGate2['data']['gate']['reason'] ?? '', 'dikonfirmasi') !== false);
$res = $tp->api('tp.final.list', ['classId' => $tpClass]);
t('sub materi mengikuti penugasan: hanya pertemuan bertugas (1 dari 4)', ($res['data']['template']['source'] ?? '') === 'assignments' && ($res['data']['template']['items'] ?? 0) === 1, json_encode($res['data']['template'] ?? null));
$scoresSeen = [];
foreach ([$e1, $e2] as $eid) {
    $g = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $eid]);
    $scoresSeen[] = $g['data']['structure'][0]['items'][0]['score'] ?? null;
}
t('nilai sub materi terisi otomatis dari nilai tugas siswa', in_array(88.5, $scoresSeen, true) && count(array_filter($scoresSeen, fn($x) => $x === null)) >= 1, json_encode($scoresSeen));
$resList = $tp->api('tp.final.list', ['classId' => $tpClass]);
$filledSeen = array_map(fn($x) => $x['filled'] . '/' . $x['count'], $resList['data']['students'] ?? []);
t('daftar menampilkan sub materi terisi sesuai tugas (1/1 dan 0/1)', in_array('1/1', $filledSeen, true) && in_array('0/1', $filledSeen, true), json_encode($filledSeen));
$asgBackup = $pdo->query("SELECT id, status FROM assignments WHERE class_id = $tpClass")->fetchAll(PDO::FETCH_KEY_PAIR);
$pdo->exec("UPDATE assignments SET status = 'draft' WHERE class_id = $tpClass");
$res = $tp->api('tp.final.list', ['classId' => $tpClass]);
t('angkatan tanpa tugas terbit: susunan dari kurikulum (4 sub materi)', ($res['data']['template']['source'] ?? '') === 'curriculum' && ($res['data']['template']['items'] ?? 0) === 4);
$pdo->exec("INSERT IGNORE INTO class_lesson_progress (class_id, enrollment_id, student_id, lesson_id, completed_by)
            SELECT $tpClass, e.id, e.student_id, l.id, 1 FROM enrollments e JOIN lessons l ON l.curriculum_version_id = $tpVersion WHERE e.class_id = $tpClass AND e.id = $e2");

$res = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e1]);
t('struktur awal: 2 materi', count($res['data']['structure'] ?? []) === 2 && ($res['data']['source'] ?? '') === 'curriculum', json_encode($res['message'] ?? ''));
$structure = [
    ['title' => 'MICROSOFT WORD', 'items' => [['title' => 'Mengelola format teks', 'jp' => 2, 'score' => 94], ['title' => 'Menyisipkan gambar', 'jp' => 2, 'score' => 98]]],
    ['title' => 'MICROSOFT EXCEL', 'items' => [['title' => 'Formula sederhana', 'jp' => 3, 'score' => 97], ['title' => 'Fungsi IF', 'jp' => 3, 'score' => 95], ['title' => 'Sub materi tambahan', 'jp' => 1, 'score' => 90]]],
];
$res = $tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e1, 'structure' => $structure]);
t('nilai akhir lengkap tersimpan, rata-rata 94,8', !empty($res['ok']) && $res['data']['status'] === 'lengkap' && abs($res['data']['average'] - 94.8) < 0.001 && abs($res['data']['totalJp'] - 11) < 0.001, json_encode($res['data'] ?? $res['message']));
t('keterangan (predikat) terisi', ($res['data']['predicate'] ?? '') !== '');
$tpl = (string) $pdo->query("SELECT structure FROM final_templates WHERE course_id = $tpCourse")->fetchColumn();
t('struktur otomatis menjadi template kursus (tanpa nilai)', $tpl !== '' && strpos($tpl, 'score') === false && strpos($tpl, 'Sub materi tambahan') !== false);

$res = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e2]);
t('siswa lain memakai template (nilai kosong)', ($res['data']['source'] ?? '') === 'template' && count($res['data']['structure'][1]['items'] ?? []) === 3 && array_key_exists('score', $res['data']['structure'][0]['items'][0] ?? []) && $res['data']['structure'][0]['items'][0]['score'] === null, json_encode($res['data']['source'] ?? $res['message']));
$partial = $structure;
$partial[1]['items'][2]['score'] = null;
$res = $tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e2, 'structure' => $partial]);
t('nilai belum lengkap menjadi draf tanpa rata-rata', !empty($res['ok']) && $res['data']['status'] === 'draf' && $res['data']['average'] === null, json_encode($res['data'] ?? $res['message']));
t('nilai di luar 0-100 ditolak', empty($tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e2, 'structure' => array_replace_recursive($structure, [0 => ['items' => [0 => ['score' => 120]]]])])['ok']));
t('materi tanpa sub materi ditolak', empty($tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e2, 'structure' => [['title' => 'Kosong', 'items' => []]]])['ok']));
$res = $tp->api('tp.final.send', ['classId' => $tpClass]);
t('kirim ditolak selama ada siswa belum lengkap', empty($res['ok']) && strpos($res['message'] ?? '', '1 siswa') !== false, $res['message'] ?? '');

// Template diubah: siswa lama tetap memakai strukturnya (opsi A)
$res = $tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e2, 'structure' => $structure]);
t('siswa kedua lengkap', ($res['data']['status'] ?? '') === 'lengkap');
$changed = $structure;
$changed[0]['title'] = 'MICROSOFT WORD 2021';
$changed[0]['items'][] = ['title' => 'Mail merge', 'jp' => 2, 'score' => 96];
$res = $tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e2, 'structure' => $changed]);
$res = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e1]);
t('struktur siswa lama tidak ikut berubah (opsi A)', ($res['data']['structure'][0]['title'] ?? '') === 'MICROSOFT WORD' && count($res['data']['structure'][0]['items'] ?? []) === 2);
$res = $tp->api('tp.final.list', ['classId' => $tpClass]);
t('template terbaru dipakai (6 sub materi)', ($res['data']['template']['items'] ?? 0) === 6, json_encode($res['data']['template'] ?? null));
foreach ($asgBackup as $aid => $ast) { $pdo->exec("UPDATE assignments SET status = '$ast' WHERE id = " . (int) $aid); }

$res = $tp->api('tp.final.send', ['classId' => $tpClass]);
t('kirim nilai akhir berhasil bila semua lengkap', !empty($res['ok']), $res['message'] ?? '');
t('kirim dua kali ditolak', empty($tp->api('tp.final.send', ['classId' => $tpClass])['ok']));
t('nilai tidak bisa diubah setelah dikirim', empty($tp->api('tp.final.save', ['classId' => $tpClass, 'enrollmentId' => $e1, 'structure' => $structure])['ok']));
$res = $tp->api('tp.final.get', ['classId' => $tpClass, 'enrollmentId' => $e1]);
t('data terkirim ditandai sentAt', !empty($res['data']['sentAt']));

// ---- Penerbitan sertifikat ----
reset_rl();
$certAdmin = new Client($base);
$certAdmin->api('auth.login', ['email' => $accounts['admin'][0], 'password' => $demoPass]);
$certStu = new Client($base);
$certStu->api('auth.login', ['email' => $accounts['peserta'][0], 'password' => $demoPass]);
$res = $certAdmin->api('cert.queue');
$certQ = null;
foreach ($res['data']['classes'] ?? [] as $qc) { if ((int) $qc['classId'] === $tpClass) { $certQ = $qc; } }
t('antrean sertifikat memuat angkatan yang nilainya terkirim', $certQ !== null && count($certQ['students']) === 2, json_encode($res['message'] ?? ''));
t('cert.queue ditolak untuk peserta dan anonim', empty($certStu->api('cert.queue')['ok']) && empty((new Client($base))->api('cert.queue')['ok']));
t('cert.issue ditolak untuk peserta', empty($certStu->api('cert.issue', ['classId' => $tpClass, 'enrollmentIds' => [$e1]])['ok']));
$res = $certAdmin->api('cert.issue', ['classId' => $tpClass, 'enrollmentIds' => [$e1, $e2]]);
t('admin menerbitkan sertifikat peserta', !empty($res['ok']) && ($res['data']['issued'] ?? 0) === 2, json_encode($res));
t('terbit ulang ditolak', empty($certAdmin->api('cert.issue', ['classId' => $tpClass, 'enrollmentIds' => [$e1]])['ok']));
$rows = $pdo->query("SELECT * FROM certificates WHERE class_id = $tpClass ORDER BY cert_seq")->fetchAll(PDO::FETCH_ASSOC);
$yr = date('Y');
t('format nomor EC / urut / kode / romawi / tahun', count($rows) === 2 && preg_match('#^EC / \d{6} / [A-Z0-9]{1,5} / (I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII) / ' . $yr . '$#', $rows[0]['certificate_number']) === 1, $rows[0]['certificate_number'] ?? '');
t('nomor berurutan dan unik', (int) $rows[1]['cert_seq'] === (int) $rows[0]['cert_seq'] + 1 && $rows[0]['certificate_number'] !== $rows[1]['certificate_number']);
$tok = $rows[0]['public_token'];
$res = (new Client($base))->api('cert.verify', ['token' => $tok]);
t('verifikasi publik via token tanpa login', !empty($res['ok']) && $res['data']['certificate']['number'] === $rows[0]['certificate_number'] && !empty($res['data']['certificate']['groups']), json_encode($res['message'] ?? ''));
t('verifikasi publik via nomor', !empty((new Client($base))->api('cert.verify', ['number' => $rows[0]['certificate_number']])['ok']));
t('verifikasi token palsu ditolak', empty((new Client($base))->api('cert.verify', ['token' => 'abc123'])['ok']));
$certId = (int) $rows[0]['id'];
$pdf = $certAdmin->get("/api/index.php?action=certificate.file&id=$certId&format=pdf");
t('unduh PDF sertifikat 2 halaman', $pdf['code'] === 200 && substr($pdf['body'], 0, 4) === '%PDF' && substr_count($pdf['body'], '/Type /Page' . "\n") + substr_count($pdf['body'], '/Type /Page ') >= 2, (string) $pdf['code']);
$png = $certAdmin->get("/api/index.php?action=certificate.file&id=$certId&format=png&side=back");
t('unduh PNG belakang', $png['code'] === 200 && substr($png['body'], 1, 3) === 'PNG');
$jpg = $certAdmin->get("/api/index.php?action=certificate.file&id=$certId&format=jpg&side=front");
t('unduh JPG depan', $jpg['code'] === 200 && substr($jpg['body'], 0, 2) === "\xFF\xD8");
t('unduh ditolak tanpa login', (new Client($base))->get("/api/index.php?action=certificate.file&id=$certId&format=pdf")['code'] === 403);
$vp = (new Client($base))->get('/verify-certificate.php?c=' . $tok);
t('halaman verifikasi publik terbuka tanpa login', $vp['code'] === 200 && strpos($vp['body'], 'verify-certificate') !== false, (string) $vp['code']);
t('verifikasi.js memakai aksi cert.verify dan token QR', strpos(file_get_contents(__DIR__ . '/../assets/js/pages/verify-certificate.js'), "cert.verify") !== false);
foreach (['admin/certificate-issue.php' => $certAdmin] as $pg => $cl) {
    $r = $cl->get("/$pg");
    t("halaman $pg memuat css & js penerbitan", $r['code'] === 200 && strpos($r['body'], 'certificate-issue.css') !== false && strpos($r['body'], 'certificate-issue.js') !== false && strpos($r['body'], 'cisList') !== false, (string) $r['code']);
}
t('sidebar admin memuat Terbit Sertifikat', strpos($certAdmin->get('/admin/dashboard.php')['body'], 'admin/certificate-issue.php') !== false);
$pdo->exec("DELETE FROM certificates WHERE class_id = $tpClass");

foreach (['instructor/angkatan.php', 'instructor/kurikulum.php', 'instructor/penilaian.php'] as $pg) {
    $r = $tp->get("/$pg");
    t("halaman $pg memuat tentor-app.css dan tp-core", $r['code'] === 200 && strpos($r['body'], 'tentor-app.css') !== false && strpos($r['body'], 'tp-core.js') !== false);
}
t('sidebar tentor memuat menu baru', strpos($tp->get('/instructor/dashboard.php')['body'], 'instructor/penilaian.php') !== false);

// Bersihkan data uji portal tentor
$pdo->exec("DELETE FROM classes WHERE id IN ($tpClass, $tpClass2)");
$pdo->exec("DELETE FROM users WHERE email LIKE 'tp-siswa%@example.com'");
$pdo->exec("DELETE FROM courses WHERE id = $tpCourse");


// ============================================================
echo "\n== Publikasi kursus (struktur judul, salin, angkatan baru)\n";
$cpTentor = (int) $pdo->query("SELECT id FROM users WHERE email = 'ahmad@example.com'")->fetchColumn();
reset_rl();
$cpLogin = function (string $label) use ($base, $accounts, $demoPass): Client {
    $cl = new Client($base);
    $cl->api('auth.login', ['email' => $accounts[$label][0], 'password' => $demoPass]);
    return $cl;
};
$cpAdmin = $cpLogin('admin');
$cpOps = $cpLogin('karyawan-operasional');
$cpMarketing = $cpLogin('karyawan-marketing');
$cpTentorClient = $cpLogin('instruktur');

t('cp.get ditolak tanpa login', empty((new Client($base))->api('cp.get', ['courseId' => 1])['ok']));
t('cp.get ditolak untuk tentor', empty($cpTentorClient->api('cp.get', ['courseId' => 1])['ok']));
t('cp.get ditolak untuk marketing', empty($cpMarketing->api('cp.get', ['courseId' => 1])['ok']));

$res = $cpAdmin->api('course.create', ['title' => 'MS Office 12', 'category' => 'Uji', 'description' => 'Uji publikasi', 'price' => 900000, 'instructorId' => $cpTentor]);
$cpCourse = (int) ($res['data']['id'] ?? 0);
t('kursus uji publikasi dibuat', $cpCourse > 0, $res['message'] ?? json_encode($res));

$res = $cpAdmin->api('cp.get', ['courseId' => $cpCourse]);
t('cp.get ok tanpa snapshot', !empty($res['ok']) && !isset($res['snapshot']), $res['message'] ?? '');
t('kursus kosong: belum bisa publikasi', ($res['data']['canPublish'] ?? true) === false && ($res['data']['totals']['lessons'] ?? -1) === 0);
t('saran angkatan pertama = judul kursus berangka', ($res['data']['suggest']['name'] ?? '') === 'MS Office 12', $res['data']['suggest']['name'] ?? '');
t('publikasi kosong ditolak server', empty($cpAdmin->api('course.status', ['id' => $cpCourse, 'status' => 'published'])['ok']));

// Simpan struktur
$structure = [
    ['title' => 'Microsoft Word', 'lessons' => [['title' => 'Format teks'], ['title' => 'Sisipkan gambar']]],
    ['title' => 'Microsoft Excel', 'lessons' => [['title' => 'Rumus dasar'], ['title' => 'Fungsi IF']]],
];
// Modul = pertemuan; materi opsional. Modul tanpa materi tetap bisa dipublikasikan.
$rc = $cpAdmin->api('course.create', ['title' => 'Uji Modul Saja', 'category' => 'Uji', 'description' => 'x', 'price' => 500000]);
$mcId = (int) ($rc['data']['id'] ?? 0);
$rs = $cpAdmin->api('cp.structure.save', ['courseId' => $mcId, 'modules' => [['title' => 'Pertemuan 1: Dasar', 'lessons' => []], ['title' => 'Pertemuan 2: Lanjut', 'lessons' => []]]]);
t('struktur modul tanpa materi tersimpan', !empty($rs['ok']), $rs['message'] ?? '');
$tr = $rs['data']['modules'] ?? [];
t('modul tanpa materi mendapat materi bawaan tersembunyi', count($tr) === 2 && !empty($tr[0]['lessons'][0]['auto']) && $tr[0]['lessons'][0]['title'] === 'Dasar', json_encode($tr[0] ?? []));
t('checklist hanya butuh modul + harga', !empty($rs['data']['canPublish']));
$rp = $cpAdmin->api('course.status', ['id' => $mcId, 'status' => 'published']);
t('publikasi tanpa materi sungguhan berhasil', !empty($rp['ok']), $rp['message'] ?? '');
$rl = $cpAdmin->api('cp.structure.save', ['courseId' => $mcId, 'modules' => [
    ['id' => (int) $tr[0]['id'], 'title' => 'Pertemuan 1: Dasar', 'lessons' => [['id' => (int) $tr[0]['lessons'][0]['id'], 'title' => 'Dasar'], ['title' => 'Latihan tambahan']]],
    ['id' => (int) $tr[1]['id'], 'title' => 'Pertemuan 2: Lanjut', 'lessons' => [['id' => (int) $tr[1]['lessons'][0]['id'], 'title' => 'Lanjut']]],
]]);
t('materi opsional ditambahkan, materi bawaan jadi materi biasa', !empty($rl['ok']) && empty($rl['data']['modules'][0]['lessons'][0]['auto']) && count($rl['data']['modules'][0]['lessons']) === 2, $rl['message'] ?? '');

$res = $cpOps->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => $structure]);
t('operasional boleh menyusun struktur', !empty($res['ok']) && ($res['data']['totals']['lessons'] ?? 0) === 4, $res['message'] ?? '');
t('struktur siap publikasi', !empty($res['data']['canPublish']), json_encode($res['data']['checklist'] ?? []));
$m1 = $res['data']['modules'][0] ?? [];
$m2 = $res['data']['modules'][1] ?? [];
t('nomor sesi berurutan lintas modul', ($m2['lessons'][0]['number'] ?? 0) === 3 && ($m2['lessons'][1]['number'] ?? 0) === 4);
t('judul kosong ditolak', empty($cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => [['title' => '', 'lessons' => []]]])['ok']));
t('judul materi kosong ditolak', empty($cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => [['title' => 'X', 'lessons' => [['title' => ' ']]]]])['ok']));
t('id materi asing ditolak', empty($cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => [['title' => 'X', 'lessons' => [['id' => 99999999, 'title' => 'Y']]]]])['ok']));

// Rename + reorder + tambah, id lama dipertahankan
$l = fn($mod, $i) => ['id' => $mod['lessons'][$i]['id'], 'title' => $mod['lessons'][$i]['title']];
$structure2 = [
    ['id' => $m2['id'], 'title' => 'Microsoft Excel', 'lessons' => [$l($m2, 0), $l($m2, 1), ['title' => 'Grafik']]],
    ['id' => $m1['id'], 'title' => 'Microsoft Word (revisi)', 'lessons' => [$l($m1, 1), $l($m1, 0)]],
];
$res = $cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => $structure2]);
$mods = $res['data']['modules'] ?? [];
t('urutan modul & materi berubah, id tetap', !empty($res['ok']) && ($mods[0]['id'] ?? 0) === $m2['id'] && ($mods[1]['title'] ?? '') === 'Microsoft Word (revisi)' && ($mods[1]['lessons'][0]['title'] ?? '') === 'Sisipkan gambar', $res['message'] ?? '');
t('materi baru ditambahkan (total 5)', ($res['data']['totals']['lessons'] ?? 0) === 5);

// Isi tentor terdeteksi
$excelLesson = (int) $mods[0]['lessons'][0]['id'];
$pdo->prepare("INSERT INTO lesson_details (lesson_id, objective, duration_jp) VALUES (?, 'Memahami rumus', 2)")->execute([$excelLesson]);
$res = $cpAdmin->api('cp.get', ['courseId' => $cpCourse]);
t('materi yang diisi tentor ditandai terisi', ($res['data']['totals']['filled'] ?? 0) === 1 && $res['data']['modules'][0]['lessons'][0]['filled'] === true);

// Hapus materi
$trim = [
    ['id' => $m2['id'], 'title' => 'Microsoft Excel', 'lessons' => [$l($m2, 0), $l($m2, 1)]],
    ['id' => $m1['id'], 'title' => 'Microsoft Word', 'lessons' => [$l($m1, 1), $l($m1, 0)]],
];
$res = $cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => $trim]);
t('hapus materi lewat daftar baru', !empty($res['ok']) && ($res['data']['totals']['lessons'] ?? 0) === 4, $res['message'] ?? '');
t('isi tentor materi yang dipertahankan tetap ada', ($res['data']['totals']['filled'] ?? 0) === 1);

// Angkatan sebelum publikasi ditolak
$res = $cpAdmin->api('cp.angkatan.create', ['courseId' => $cpCourse, 'periodStart' => '2027-02-01', 'quota' => 10, 'weekdays' => [1, 3], 'startTime' => '09:00', 'endTime' => '11:00']);
t('angkatan sebelum publikasi ditolak', empty($res['ok']), $res['message'] ?? '');

// Publikasi
$res = $cpAdmin->api('course.status', ['id' => $cpCourse, 'status' => 'published']);
t('kursus berstruktur dapat dipublikasikan', !empty($res['ok']), $res['message'] ?? '');

// Angkatan baru
$base1 = ['courseId' => $cpCourse, 'periodStart' => '2027-02-01', 'quota' => 12, 'weekdays' => [1, 3], 'startTime' => '09:00', 'endTime' => '11:00', 'jp' => 2, 'location' => 'Bandung'];
t('angkatan tanpa hari belajar ditolak', empty($cpOps->api('cp.angkatan.create', array_merge($base1, ['weekdays' => []]))['ok']));
t('angkatan jam terbalik ditolak', empty($cpOps->api('cp.angkatan.create', array_merge($base1, ['startTime' => '11:00', 'endTime' => '09:00']))['ok']));
t('angkatan kuota 0 ditolak', empty($cpOps->api('cp.angkatan.create', array_merge($base1, ['quota' => 0]))['ok']));
$res = $cpOps->api('cp.angkatan.create', $base1);
$cpClass = (int) ($res['data']['createdClassId'] ?? 0);
t('operasional membuat angkatan 1 (nama otomatis)', !empty($res['ok']) && $cpClass > 0 && ($res['data']['createdSessions'] ?? 0) === 4, $res['message'] ?? '');
$row = $pdo->query("SELECT * FROM classes WHERE id = $cpClass")->fetch();
t('angkatan memakai tentor kursus dan status open', $row && (int) $row['instructor_id'] === $cpTentor && $row['status'] === 'open' && $row['name'] === 'MS Office 12', json_encode($row));
$ses = $pdo->query("SELECT session_number, title, session_date, DAYOFWEEK(session_date) AS dow FROM class_sessions WHERE class_id = $cpClass ORDER BY session_number")->fetchAll();
t('4 sesi dibuat, judul = materi ke-N', count($ses) === 4 && $ses[0]['title'] === 'Rumus dasar' && $ses[3]['title'] === 'Format teks', json_encode($ses));
t('tanggal sesi: Senin & Rabu mulai 1 Feb 2027', ($ses[0]['session_date'] ?? '') === '2027-02-01' && ($ses[1]['session_date'] ?? '') === '2027-02-03' && ($ses[2]['session_date'] ?? '') === '2027-02-08', json_encode(array_column($ses, 'session_date')));
t('akhir periode = tanggal sesi terakhir', ($row['period_end'] ?? '') === ($ses[3]['session_date'] ?? 'x'), $row['period_end'] ?? '');
$vstatus = $pdo->query("SELECT status FROM curriculum_versions WHERE id = " . (int) $row['curriculum_version_id'])->fetchColumn();
t('kurikulum bersama TIDAK dikunci saat angkatan dibuat', $vstatus === 'published', (string) $vstatus);
t('tentor diberi notifikasi angkatan baru', (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'angkatan_baru' AND entity_id = $cpClass")->fetchColumn() === 1);
t('nama ganda ditolak', empty($cpOps->api('cp.angkatan.create', array_merge($base1, ['name' => 'MS Office 12']))['ok']));

$res = $cpAdmin->api('cp.get', ['courseId' => $cpCourse]);
t('saran berikutnya = MS Office 13', ($res['data']['suggest']['name'] ?? '') === 'MS Office 13', $res['data']['suggest']['name'] ?? '');
t('bawaan form mengikuti angkatan terakhir (Sen & Rab, 12 kuota)', ($res['data']['suggest']['defaults']['weekdays'] ?? []) === [1, 3] && ($res['data']['suggest']['defaults']['quota'] ?? 0) === 12, json_encode($res['data']['suggest']['defaults'] ?? []));

$res = $cpAdmin->api('cp.angkatan.create', array_merge($base1, ['periodStart' => '2027-06-07']));
$cpClass2 = (int) ($res['data']['createdClassId'] ?? 0);
t('angkatan 2 dibuat otomatis bernama MS Office 13', !empty($res['ok']) && (string) $pdo->query("SELECT name FROM classes WHERE id = $cpClass2")->fetchColumn() === 'MS Office 13', $res['message'] ?? '');

// Tentor melihat angkatan baru & dapat mengisi (kurikulum bersama, tidak terkunci)
$res = $cpTentorClient->api('tp.curriculum.courses');
$mine = array_values(array_filter($res['data']['courses'] ?? $res['data'] ?? [], fn($c) => (int) ($c['courseId'] ?? 0) === $cpCourse));
t('tentor melihat kursus angkatan baru di portalnya', count($mine) === 1, json_encode(array_keys($res['data'] ?? [])));
$res = $cpTentorClient->api('tp.curriculum.get', ['courseId' => $cpCourse]);
$cpVerId = (int) ($res['data']['version']['id'] ?? $res['data']['versions'][0]['id'] ?? 0);
t('tentor dapat membuka kurikulum angkatan baru', !empty($res['ok']), $res['message'] ?? '');

// Sesi terkunci melindungi struktur
$pdo->exec("UPDATE classes SET status = 'ongoing' WHERE id = $cpClass");
$pdo->exec("UPDATE class_sessions SET status = 'confirmed' WHERE class_id = $cpClass AND session_number = 1");
$cur = $cpAdmin->api('cp.get', ['courseId' => $cpCourse])['data']['modules'] ?? [];
t('sesi 1 ditandai terkunci', ($cur[0]['lessons'][0]['locked'] ?? false) === true);
$try = $cur; $try[0]['lessons'][0]['title'] = 'Diganti';
t('ubah judul sesi terkunci ditolak', empty($cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => $try])['ok']));
$try = $cur; array_shift($try[0]['lessons']);
t('hapus sesi terkunci ditolak', empty($cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => $try])['ok']));
$try = $cur; $try[0]['title'] = 'Excel Lanjut';
$res = $cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => $try]);
t('ubah judul modul tetap boleh', !empty($res['ok']), $res['message'] ?? '');
// Judul sesi yang belum berjalan ikut tersinkron ke jadwal
$try = $res['data']['modules']; $try[1]['lessons'][1]['title'] = 'Format teks lanjutan';
$res = $cpAdmin->api('cp.structure.save', ['courseId' => $cpCourse, 'modules' => $try]);
$syn = $pdo->query("SELECT title FROM class_sessions WHERE class_id = $cpClass AND session_number = 4")->fetchColumn();
t('judul sesi terjadwal ikut diperbarui', !empty($res['ok']) && $syn === 'Format teks lanjutan', (string) $syn . ' | ' . ($res['message'] ?? ''));

// Salin struktur
$res = $cpAdmin->api('course.create', ['title' => 'Kursus Salinan Uji', 'category' => 'Uji', 'description' => 'Uji', 'price' => 500000, 'instructorId' => $cpTentor]);
$cpCopy = (int) ($res['data']['id'] ?? 0);
$res = $cpAdmin->api('cp.get', ['courseId' => $cpCopy]);
t('kursus lain muncul sebagai sumber salin', in_array($cpCourse, array_column($res['data']['copySources'] ?? [], 'id'), true));
$res = $cpOps->api('cp.structure.copy', ['courseId' => $cpCopy, 'fromCourseId' => $cpCourse]);
t('salin struktur: 2 modul, 4 materi, tanpa isi tentor', !empty($res['ok']) && ($res['data']['totals']['modules'] ?? 0) === 2 && ($res['data']['totals']['lessons'] ?? 0) === 4 && ($res['data']['totals']['filled'] ?? -1) === 0, $res['message'] ?? '');
t('salin ke kursus berisi ditolak', empty($cpOps->api('cp.structure.copy', ['courseId' => $cpCopy, 'fromCourseId' => $cpCourse])['ok']));
t('salin dari diri sendiri ditolak', empty($cpOps->api('cp.structure.copy', ['courseId' => $cpCourse, 'fromCourseId' => $cpCourse])['ok']));


// ---- Status angkatan otomatis & tutup pembelajaran
$pastStart = (new DateTimeImmutable('-3 days', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$res = $cpOps->api('cp.angkatan.create', array_merge($base1, ['periodStart' => $pastStart]));
$lcClass = (int) ($res['data']['createdClassId'] ?? 0);
t('angkatan dengan tanggal mulai sudah lewat langsung Berlangsung', !empty($res['ok']) && (string) $pdo->query("SELECT status FROM classes WHERE id = $lcClass")->fetchColumn() === 'ongoing', $res['message'] ?? '');
$lcCourse = $cpCourse;
$pub = (new Client($base))->api('course.classes', ['courseId' => $lcCourse]);
t('angkatan Berlangsung tidak tampil di pendaftaran publik', !in_array($lcClass, array_column($pub['data'] ?? [], 'id'), true));
$res = (new Client($base))->api('course.register', ['courseId' => $lcCourse, 'classId' => $lcClass, 'name' => 'Telat Daftar', 'birthPlace' => 'X', 'birthDate' => '2000-01-01', 'address' => 'Jl. X No. 1', 'phone' => '081200000099', 'email' => 'telat@example.com']);
t('pendaftaran ke angkatan Berlangsung ditolak', empty($res['ok']), $res['message'] ?? '');
foreach (['open', 'ready', 'ongoing', 'draft', 'completed'] as $manual) {
    t("status manual '$manual' ditolak", empty($cpAdmin->api('class.status', ['id' => $lcClass, 'status' => $manual])['ok']));
}
$endBefore = (string) $pdo->query("SELECT period_end FROM classes WHERE id = $lcClass")->fetchColumn();
$lastSes = (string) $pdo->query("SELECT MAX(session_date) FROM class_sessions WHERE class_id = $lcClass")->fetchColumn();
t('perkiraan selesai = tanggal sesi terakhir', $endBefore === $lastSes, "$endBefore / $lastSes");
$cpAdmin->api('class.update', ['id' => $lcClass, 'data' => ['periodEnd' => '2099-01-01', 'quota' => 15]]);
t('tanggal selesai tidak bisa diubah manual', (string) $pdo->query("SELECT period_end FROM classes WHERE id = $lcClass")->fetchColumn() === $endBefore);
$pdo->exec("UPDATE class_sessions SET session_date = DATE_ADD(session_date, INTERVAL 7 DAY) WHERE class_id = $lcClass AND session_number = 4");
$cpAdmin->api('cp.get', ['courseId' => $lcCourse]);
t('perkiraan selesai ikut bergeser bila jadwal digeser', (string) $pdo->query("SELECT period_end FROM classes WHERE id = $lcClass")->fetchColumn() === (string) $pdo->query("SELECT MAX(session_date) FROM class_sessions WHERE class_id = $lcClass")->fetchColumn());

// Tutup pembelajaran
t('tutup ditolak untuk marketing', empty($cpMarketing->api('class.close', ['id' => $lcClass])['ok']));
t('tutup ditolak untuk tentor', empty($cpTentorClient->api('class.close', ['id' => $lcClass])['ok']));
t('angkatan Pendaftaran tidak bisa ditutup', empty($cpOps->api('class.close', ['id' => $cpClass2])['ok']));
$res = $cpOps->api('class.close', ['id' => $lcClass]);
t('tutup meminta konfirmasi bila ada yang belum selesai', empty($res['ok']) && !empty($res['data']['needsConfirm']) && count($res['data']['warnings'] ?? []) >= 1, json_encode($res));
t('status tetap Berlangsung sebelum dikonfirmasi', (string) $pdo->query("SELECT status FROM classes WHERE id = $lcClass")->fetchColumn() === 'ongoing');
$res = $cpOps->api('class.close', ['id' => $lcClass, 'confirm' => true]);
t('operasional menutup pembelajaran (Selesai)', !empty($res['ok']) && ($res['data']['status'] ?? '') === 'completed' && !empty($res['data']['completedAt']), $res['message'] ?? '');
t('penutupan dicatat siapa & kapan', (int) $pdo->query("SELECT completed_by FROM classes WHERE id = $lcClass")->fetchColumn() > 0);
t('tutup dua kali ditolak', empty($cpOps->api('class.close', ['id' => $lcClass, 'confirm' => true])['ok']));
t('status Selesai tidak diubah otomatis', (string) $pdo->query("SELECT status FROM classes WHERE id = $lcClass")->fetchColumn() === 'completed');
t('angkatan Selesai hanya lihat (ubah ditolak)', empty($cpAdmin->api('class.update', ['id' => $lcClass, 'data' => ['quota' => 30]])['ok']));
t('tentor tetap bisa melihat daftar nilai akhir angkatan Selesai', !empty($cpTentorClient->api('tp.final.list', ['classId' => $lcClass])['ok']));
t('tentor tetap bisa melihat detail angkatan Selesai', !empty($cpTentorClient->api('tp.class.detail', ['classId' => $lcClass])['ok']));

// Buka kembali
t('buka kembali tanpa alasan ditolak', empty($cpAdmin->api('class.reopen', ['id' => $lcClass, 'reason' => ' '])['ok']));
t('buka kembali ditolak untuk tentor', empty($cpTentorClient->api('class.reopen', ['id' => $lcClass, 'reason' => 'x'])['ok']));
t('buka kembali ditolak untuk angkatan yang belum Selesai', empty($cpAdmin->api('class.reopen', ['id' => $cpClass2, 'reason' => 'x'])['ok']));
$res = $cpAdmin->api('class.reopen', ['id' => $lcClass, 'reason' => 'Ada sesi susulan']);
t('admin membuka kembali angkatan Selesai', !empty($res['ok']) && ($res['data']['status'] ?? '') === 'ongoing', $res['message'] ?? '');
t('buka kembali tercatat di audit', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity = 'class' AND detail LIKE '%Ada sesi susulan%'")->fetchColumn() === 1);

// Geser tanggal mulai ke depan
$future = (new DateTimeImmutable('+20 days', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$pdo->exec("UPDATE class_sessions SET status = 'confirmed' WHERE class_id = $lcClass AND session_number = 1");
t('geser mulai ke depan ditolak bila sudah ada sesi terkonfirmasi', empty($cpAdmin->api('class.update', ['id' => $lcClass, 'data' => ['periodStart' => $future]])['ok']));
$pdo->exec("UPDATE class_sessions SET status = 'scheduled' WHERE class_id = $lcClass");
$res = $cpAdmin->api('class.update', ['id' => $lcClass, 'data' => ['periodStart' => $future]]);
$cpAdmin->api('cp.get', ['courseId' => $lcCourse]);
t('geser mulai ke depan: kembali ke Pendaftaran', !empty($res['ok']) && (string) $pdo->query("SELECT status FROM classes WHERE id = $lcClass")->fetchColumn() === 'open', $res['message'] ?? '');
$pdo->exec("UPDATE classes SET status = 'ongoing', period_start = '$pastStart' WHERE id = $lcClass");
$pdo->exec("UPDATE classes SET status = 'draft', period_start = NULL WHERE id = $cpClass2");
$cpAdmin->api('cp.get', ['courseId' => $lcCourse]);
t('status Draft lama tanpa tanggal mulai menjadi Pendaftaran', (string) $pdo->query("SELECT status FROM classes WHERE id = $cpClass2")->fetchColumn() === 'open');
$pdo->exec("UPDATE classes SET status = 'ready', period_start = '$pastStart' WHERE id = $cpClass2");
$cpAdmin->api('cp.get', ['courseId' => $lcCourse]);
t('status Siap lama dengan tanggal lewat menjadi Berlangsung', (string) $pdo->query("SELECT status FROM classes WHERE id = $cpClass2")->fetchColumn() === 'ongoing');
$pdo->exec("DELETE FROM classes WHERE id = $lcClass");

$cpJs = (string) file_get_contents(__DIR__ . '/../assets/js/pages/admin/course-publish.js');
t('tombol publikasi dikunci bila struktur kosong/belum disimpan', strpos($cpJs, 'function publishBlockReason') !== false && strpos($cpJs, 'btn.disabled = reason') !== false);
t('tombol angkatan baru dikunci sebelum publikasi', strpos($cpJs, 'btn.disabled = !ready') !== false);

// Halaman
foreach (['admin' => ['admin/course-publish.php?id=' . $cpCourse, $cpAdmin], 'karyawan-operasional' => ['employee/operasional/kursus-publikasi.php?id=' . $cpCourse, $cpOps]] as $who => [$pg, $cl]) {
    $r = $cl->get("/$pg");
    t("halaman publikasi ($who) memuat css & js", $r['code'] === 200 && strpos($r['body'], 'course-publish.css') !== false && strpos($r['body'], 'course-publish.js') !== false && strpos($r['body'], 'cpbTree') !== false, (string) $r['code']);
}
foreach (['admin/courses.php' => $cpAdmin, 'employee/operasional/kursus.php' => $cpOps] as $pg => $cl) {
    t("halaman $pg memuat course-angkatan.css dan modal ubah angkatan", strpos($cl->get("/$pg")['body'], 'course-angkatan.css') !== false && strpos($cl->get("/$pg")['body'], 'clsModal') !== false);
}
t('courses.js memuat daftar angkatan per kursus', strpos($cpAdmin->get('/assets/js/pages/admin/courses.js')['body'], 'cls-edit') !== false);
t('courses.js mengarah ke halaman publikasi', strpos($cpAdmin->get('/assets/js/pages/admin/courses.js')['body'], 'course-publish.php') !== false);

// Bersihkan
$pdo->exec("DELETE FROM classes WHERE id IN ($cpClass, $cpClass2)");
$pdo->exec("DELETE FROM courses WHERE id IN ($cpCourse, $cpCopy)");

proc_terminate($srv);
if (!$hadLock) { @unlink($lock); }
echo "\n----------------------------------------\n";
echo "Lulus: $passed | Gagal: " . count($failed) . "\n";
if ($failed) {
    echo "Gagal:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}
exit(0);