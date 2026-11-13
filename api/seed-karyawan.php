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
    ['Dewi Lestari', 'dewi.karyawan@example.com', '081234567801', 'marketing', '2026-10-05'],
    ['Agus Prasetyo', 'agus.karyawan@example.com', '081234567802', 'operasional', '2026-10-06'],
    ['Sri Wahyuni', 'sri.karyawan@example.com', '081234567803', 'keuangan', '2026-10-07'],
    ['Joko Susilo', 'joko.karyawan@example.com', '081234567804', 'hrd', '2026-10-08'],
    ['Ratna Sari', 'ratna.karyawan@example.com', '081234567805', 'marketing', '2026-10-09'],
    ['Hendra Gunawan', 'hendra.karyawan@example.com', '081234567806', 'operasional', '2026-10-10'],
    ['Maya Putri', 'maya.karyawan@example.com', '081234567807', 'keuangan', '2026-10-11'],
    ['Eko Nugroho', 'eko.karyawan@example.com', '081234567808', 'hrd', '2026-10-12'],
    ['Nur Aisyah', 'nur.karyawan@example.com', '081234567809', 'marketing', '2026-10-13'],
    ['Bayu Pratama', 'bayu.karyawan@example.com', '081234567810', 'operasional', '2026-10-14'],
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

    // Pastikan kolom divisi tersedia pada database lama.
    $divisionColumn = $db->query("SHOW COLUMNS FROM users LIKE 'division'")->fetch();
    if (!$divisionColumn) {
        $db->exec("ALTER TABLE users ADD COLUMN division VARCHAR(40) NOT NULL DEFAULT '' AFTER role");
        $db->exec('ALTER TABLE users ADD KEY idx_users_division (division)');
        echo "Kolom divisi karyawan ditambahkan.\n";
    }

    $find = $db->prepare('SELECT id FROM users WHERE email = ?');
    $insert = $db->prepare(
        'INSERT INTO users (name, email, phone, password_hash, role, division, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $update = $db->prepare(
        "UPDATE users SET name = ?, phone = ?, division = ?, role = 'karyawan', status = 'aktif'
         WHERE email = ?"
    );

    $created = 0;
    $updated = 0;

    $db->beginTransaction();
    foreach ($employees as [$name, $email, $phone, $division, $createdAt]) {
        $find->execute([$email]);
        $existingId = $find->fetchColumn();
        if ($existingId !== false && $existingId !== null) {
            $update->execute([$name, $phone, $division, $email]);
            $updated++;
            echo "Diperbarui: {$name} <{$email}> (id {$existingId}, divisi {$division})\n";
            continue;
        }

        $insert->execute([$name, $email, $phone, $passwordHash, 'karyawan', $division, 'aktif', $createdAt]);
        $created++;
        echo "Dibuat: {$name} <{$email}> (id {$db->lastInsertId()}, divisi {$division})\n";
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
