<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - seed akun karyawan dummy divisi Marketing
// Jalankan: php api/seed-marketing.php
// Skrip ini idempoten: akun dicocokkan lewat email, sehingga aman
// dijalankan berulang tanpa membuat duplikat.
// Password mengikuti config demo_password (default: elips123).
// ============================================================

require __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$marketing = [
    ['Andi Saputra', 'andi.marketing@example.com', '081234568101', '2026-10-20'],
    ['Bella Kartika', 'bella.marketing@example.com', '081234568102', '2026-10-21'],
    ['Candra Wijaya', 'candra.marketing@example.com', '081234568103', '2026-10-22'],
    ['Dinda Maharani', 'dinda.marketing@example.com', '081234568104', '2026-10-23'],
    ['Fajar Ramadhan', 'fajar.marketing@example.com', '081234568105', '2026-10-24'],
    ['Gita Puspita', 'gita.marketing@example.com', '081234568106', '2026-10-25'],
];

try {
    $config = elips_config();
    $db = elips_db();
    $demoPassword = (string) ($config['demo_password'] ?? 'elips123');
    $passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);

    // Pastikan role karyawan dan kolom divisi tersedia pada database lama.
    $roleColumn = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if ($roleColumn && strpos((string) $roleColumn['Type'], "'karyawan'") === false) {
        $db->exec(
            "ALTER TABLE users MODIFY role ENUM('admin', 'instruktur', 'peserta', 'karyawan')
             NOT NULL DEFAULT 'peserta'"
        );
        echo "Role karyawan ditambahkan.\n";
    }
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
        "UPDATE users SET name = ?, phone = ?, division = 'marketing', role = 'karyawan', status = 'aktif'
         WHERE email = ?"
    );

    $created = 0;
    $updated = 0;

    $db->beginTransaction();
    foreach ($marketing as [$name, $email, $phone, $createdAt]) {
        $find->execute([$email]);
        $existingId = $find->fetchColumn();
        if ($existingId !== false && $existingId !== null) {
            $update->execute([$name, $phone, $email]);
            $updated++;
            echo "Diperbarui: {$name} <{$email}> (id {$existingId}, divisi marketing)\n";
            continue;
        }

        $insert->execute([$name, $email, $phone, $passwordHash, 'karyawan', 'marketing', 'aktif', $createdAt]);
        $created++;
        echo "Dibuat: {$name} <{$email}> (id {$db->lastInsertId()}, divisi marketing)\n";
    }
    $db->commit();

    echo "\nSelesai: {$created} akun Marketing dibuat, {$updated} akun diperbarui.\n";
    echo "Role: karyawan | Divisi: marketing | Status: aktif | Password: {$demoPassword}\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Gagal membuat karyawan Marketing dummy: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
