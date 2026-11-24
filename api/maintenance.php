<?php
// Direktori: api/maintenance.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - FUNGSI SEMENTARA: reset seluruh data
//
// Menghapus semua data kecuali akun admin (baris users ber-role
// admin). Hanya untuk masa uji/persiapan sebelum dipakai nyata.
//
// Cara menonaktifkan: set variabel lingkungan ELIPS_ALLOW_DATA_RESET=0
// atau hapus file ini beserta baris require & case 'system.resetData'
// di api/actions.php dan bagian "Zona Berbahaya" di admin/settings.php.
// ============================================================

const ELIPS_RESET_CONFIRM_PHRASE = 'HAPUS SEMUA DATA';

/** Tabel pengaturan yang boleh dipertahankan bila admin memilih "pertahankan pengaturan". */
function elips_reset_settings_tables(): array
{
    return ['app_settings', 'categories', 'branches', 'predicate_ranges', 'grade_schemes', 'grade_scheme_components'];
}

function elips_reset_is_enabled(): bool
{
    return getenv('ELIPS_ALLOW_DATA_RESET') !== '0';
}

/** Hapus isi folder unggahan (kecuali .htaccess). Mengembalikan jumlah berkas terhapus. */
function elips_reset_clear_dir(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $count = 0;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || $name === '.htaccess' || $name === '.gitkeep') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_file($path) && @unlink($path)) {
            $count++;
        }
    }
    return $count;
}

function elips_action_system_reset_data(array $p): array
{
    $actor = elips_require_admin();
    if (!elips_reset_is_enabled()) {
        return elips_fail('Fitur reset data dinonaktifkan di server ini.');
    }

    $key = (string) $actor['id'];
    $wait = elips_rate_limit_blocked('reset_data', $key, 5, 600);
    if ($wait > 0) {
        return elips_fail('Terlalu banyak percobaan. Coba lagi dalam ' . ceil($wait / 60) . ' menit.');
    }

    if (trim((string) ($p['confirm'] ?? '')) !== ELIPS_RESET_CONFIRM_PHRASE) {
        return elips_fail('Ketik "' . ELIPS_RESET_CONFIRM_PHRASE . '" persis untuk melanjutkan.');
    }

    $db = elips_db();
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? AND role = \'admin\'');
    $stmt->execute([(int) $actor['id']]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify((string) ($p['password'] ?? ''), $hash)) {
        elips_rate_limit_hit('reset_data', $key, 600);
        return elips_fail('Kata sandi admin salah.');
    }
    elips_rate_limit_clear('reset_data', $key);

    $keepSettings = !empty($p['keepSettings']);
    $keep = $keepSettings ? elips_reset_settings_tables() : [];

    $tables = $db->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $summary = ['tables' => 0, 'rows' => 0, 'users' => 0, 'files' => 0];
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $table) {
            $table = (string) $table;
            if ($table === 'users' || in_array($table, $keep, true)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }
            $summary['rows'] += (int) $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $db->exec("TRUNCATE TABLE `$table`");
            $summary['tables']++;
        }

        // Semua akun selain admin dihapus; akun admin (termasuk kata sandinya) tetap.
        $summary['users'] = (int) $db->exec("DELETE FROM users WHERE role <> 'admin'");
        $db->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    } finally {
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $root = dirname(__DIR__);
    $summary['files'] += elips_reset_clear_dir($root . '/storage/payment-proofs');
    $summary['files'] += elips_reset_clear_dir($root . '/assets/uploads/courses');

    elips_audit(
        'reset', 'system', null, 'Reset data',
        'Menghapus seluruh data (' . $summary['rows'] . ' baris di ' . $summary['tables'] . ' tabel, '
            . $summary['users'] . ' akun non-admin, ' . $summary['files'] . ' berkas)'
            . ($keepSettings ? ', pengaturan dipertahankan.' : '.'),
        $actor
    );

    return elips_ok(
        'Data berhasil dibersihkan: ' . $summary['users'] . ' akun non-admin dan ' . $summary['rows'] . ' baris data dihapus.',
        $summary
    );
}