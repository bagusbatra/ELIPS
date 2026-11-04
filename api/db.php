<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - koneksi database
// ============================================================

function elips_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

/**
 * Koneksi PDO tunggal. Gunakan $withDatabase = false hanya saat instalasi.
 */
function elips_db(bool $withDatabase = true): PDO
{
    static $connections = [];
    $key = $withDatabase ? 'db' : 'server';
    if (isset($connections[$key])) {
        return $connections[$key];
    }

    $config = elips_config()['db'];
    $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'];
    if ($withDatabase) {
        $dsn .= ';dbname=' . $config['name'];
    }
    $dsn .= ';charset=utf8mb4';

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Simpan dan baca timestamp dalam UTC agar konsisten dengan ISO 8601
    // yang dikirim ke browser.
    $pdo->exec("SET time_zone = '+00:00'");

    $connections[$key] = $pdo;
    return $pdo;
}
