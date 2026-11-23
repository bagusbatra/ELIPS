<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - buat akun admin (CLI)
// php api/create-admin.php email@domain.com "Nama Admin" [--password=...]
// Tanpa --password, password acak dibuat dan ditampilkan sekali.
// Dipakai pada production karena installer tidak memasukkan akun demo.
// ============================================================

require __DIR__ . '/db.php';
require __DIR__ . '/setup-guard.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$args = array_slice($_SERVER['argv'] ?? [], 1);
$password = '';
$positional = [];
foreach ($args as $arg) {
    if (strpos($arg, '--password=') === 0) {
        $password = substr($arg, 11);
    } else {
        $positional[] = $arg;
    }
}
[$email, $name] = array_pad($positional, 2, '');
$email = strtolower(trim((string) $email));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim((string) $name) === '') {
    fwrite(STDERR, "Pemakaian: php api/create-admin.php email@domain.com \"Nama Admin\" [--password=...]\n");
    exit(1);
}
$generated = false;
if ($password === '') {
    $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    $generated = true;
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password minimal 8 karakter.\n");
    exit(1);
}

$db = elips_db();
$exists = $db->prepare('SELECT id FROM users WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetchColumn()) {
    fwrite(STDERR, "Email sudah terdaftar.\n");
    exit(1);
}
$db->prepare("INSERT INTO users (name, email, phone, password_hash, role, status, created_at) VALUES (?, ?, '', ?, 'admin', 'aktif', CURDATE())")
    ->execute([trim((string) $name), $email, password_hash($password, PASSWORD_DEFAULT)]);

echo "Admin dibuat: " . $email . "\n";
if ($generated) {
    echo "Password sementara (catat, hanya tampil sekali): " . $password . "\n";
}
