<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - konfigurasi backend
// Nilai dapat dioverride lewat environment variable sehingga
// kredensial tidak perlu diubah di kode saat deployment.
// ============================================================

return [
    'db' => [
        'host' => getenv('ELIPS_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('ELIPS_DB_PORT') ?: 3306),
        'name' => getenv('ELIPS_DB_NAME') ?: 'elips_lms',
        'user' => getenv('ELIPS_DB_USER') ?: 'root',
        'pass' => getenv('ELIPS_DB_PASS') !== false ? (string) getenv('ELIPS_DB_PASS') : '',
    ],
    // Password akun demo. Dipakai hanya oleh installer.
    'demo_password' => 'elips123',
    // Batas jumlah entri audit/notifikasi yang disimpan di database.
    'audit_limit' => 1000,
    'notification_limit' => 1000,
];
