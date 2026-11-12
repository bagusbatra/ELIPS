<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - seed 10 akun karyawan dummy
// Jalankan: php api/seed-karyawan.php
// Skrip ini idempoten: akun dicocokkan lewat email, sehingga aman
// dijalankan berulang tanpa membuat duplikat.
// Password mengikuti config demo_password (default: elips123).
// ============================================================

require __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$employees = [
    ['Dewi Lestari', 'dewi.karyawan@example.com', '081234567801', '2026-10-05'],
    ['Agus Prasetyo', 'agus.karyawan@example.com', '081234567802', '2026-10-06'],
    ['Sri Wahyuni', 'sri.karyawan@example.com', '081234567803', '2026-10-07'],
    ['Joko Susilo', 'joko.karyawan@example.com', '081234567804', '2026-10-08'],
    ['Ratna Sari', 'ratna.karyawan@example.com', '081234567805', '2026-10-09'],
    ['Hendra Gunawan', 'hendra.karyawan@example.com', '081234567806', '2026-10-10'],
    ['Maya Putri', 'maya.karyawan@example.com', '081234567807', '2026-10-11'],
    ['Eko Nugroho', 'eko.karyawan@example.com', '081234567808', '2026-10-12'],
    ['Nur Aisyah', 'nur.karyawan@example.com', '081234567809', '2026-10-13'],
    ['Bayu Pratama', 'bayu.karyawan@example.com', '081234567810', '2026-10-14'],
];

try {
    $config = elips_config();
    $db = elips_db();
    $demoPassword = (string) ($config['demo_password'] ?? 'elips123');
    $passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);

    // Pastikan role karyawan tersedia pada database lama.
    $roleColumn = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if ($roleColumn && strpos((string) $roleColumn['Type'], "'karyawan'") === false) {
        $db->exec(
            "ALTER TABLE users MODIFY role ENUM('admin', 'instruktur', 'peserta', 'karyawan')
             NOT NULL DEFAULT 'peserta'"
        );
        echo "Role karyawan ditambahkan.\n";
    }

    $find = $db->prepare('SELECT id FROM users WHERE email = ?');
    $insert = $db->prepare(
        'INSERT INTO users (name, email, phone, password_hash, role, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $update = $db->prepare(
        "UPDATE users SET name = ?, phone = ?, role = 'karyawan', status = 'aktif'
         WHERE email = ?"
    );

    $created = 0;
    $updated = 0;

    $db->beginTransaction();
    foreach ($employees as [$name, $email, $phone, $createdAt]) {
        $find->execute([$email]);
        $existingId = $find->fetchColumn();
        if ($existingId !== false && $existingId !== null) {
            $update->execute([$name, $phone, $email]);
            $updated++;
            echo "Diperbarui: {$name} <{$email}> (id {$existingId})\n";
            continue;
        }

        $insert->execute([$name, $email, $phone, $passwordHash, 'karyawan', 'aktif', $createdAt]);
        $created++;
        echo "Dibuat: {$name} <{$email}> (id {$db->lastInsertId()})\n";
    }
    $db->commit();

    echo "\nSelesai: {$created} akun dibuat, {$updated} akun diperbarui.\n";
    echo "Role: karyawan | Status: aktif | Password: {$demoPassword}\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Gagal membuat karyawan dummy: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
