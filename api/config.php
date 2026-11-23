<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - konfigurasi backend
// Nilai dapat dioverride lewat environment variable sehingga
// kredensial tidak perlu diubah di kode saat deployment.
// ============================================================

return [
    // development | production. Pada production seed demo ditolak.
    'env' => getenv('ELIPS_ENV') ?: 'development',
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
    // Aturan absensi karyawan (divalidasi di server, lihat attendance_rules.php).
    // Isi 'geofence' dengan titik kantor agar absensi di luar radius ditolak:
    //   [['name' => 'Kantor Pusat', 'lat' => -7.4478, 'lng' => 112.7183, 'radius_m' => 150]]
    // atau lewat env ELIPS_GEOFENCE (JSON). Set ELIPS_GEOFENCE_ENFORCE=1 untuk menolak
    // (tanpa itu absensi di luar radius tetap diterima tetapi diberi flag outside_geofence).
    'attendance' => [
        'geofence' => json_decode((string) (getenv('ELIPS_GEOFENCE') ?: '[]'), true) ?: [],
        'geofence_enforce' => in_array(getenv('ELIPS_GEOFENCE_ENFORCE'), ['1', 'true'], true),
        'max_accuracy_m' => (int) (getenv('ELIPS_MAX_ACCURACY_M') ?: 150),
        'default_start' => '08:00',
        'default_end' => '16:00',
        'late_grace_minutes' => 10,
        'early_grace_minutes' => 10,
    ],
    // Muat bundel JS gabungan (assets/js/dist) bila tersedia; ELIPS_BUNDLE=0 untuk berkas satuan.
    'bundle' => getenv('ELIPS_BUNDLE') !== '0',
    // Mode demo lokal tanpa server (localStorage + seed.js). Jangan aktifkan di production.
    'local_demo' => getenv('ELIPS_LOCAL_DEMO') === '1',
    'audit_limit' => 1000,
    'notification_limit' => 1000,
    // Kebijakan rekap kehadiran. Hanya status ini yang dihitung hadir,
    // dan hanya sesi berstatus ini yang masuk perhitungan JP wajib.
    'attendance_present_statuses' => ['present', 'late'],
    'attendance_counted_statuses' => ['confirmed', 'completed'],
];
