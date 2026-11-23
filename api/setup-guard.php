<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - penjaga skrip setup (install & seed)
// - Hanya boleh dijalankan lewat CLI (php api/install.php). Lewat web
//   selalu 404, jadi tidak bisa dipicu dari browser.
// - install.php menulis storage/install.lock setelah sukses dan menolak
//   berjalan lagi tanpa --force.
// - seed-*.php menolak berjalan bila ELIPS_ENV=production.
// ============================================================

function elips_install_lock_path(): string
{
    return dirname(__DIR__) . '/storage/install.lock';
}

function elips_setup_guard(string $kind): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found\n";
        exit;
    }

    $argv = $_SERVER['argv'] ?? [];
    $env = (string) (elips_config()['env'] ?? 'development');

    if ($kind === 'seed' && $env === 'production') {
        fwrite(STDERR, "Ditolak: seed data demo tidak boleh dijalankan saat ELIPS_ENV=production.\n");
        exit(1);
    }

    if ($kind === 'install' && is_file(elips_install_lock_path()) && !in_array('--force', $argv, true)) {
        fwrite(STDERR, "Ditolak: instalasi sudah pernah dijalankan (storage/install.lock).\n"
            . "Jalankan ulang dengan --force bila memang ingin memigrasi/menimpa.\n");
        exit(1);
    }
    if ($kind === 'install' && in_array('--fresh', $argv, true) && !in_array('--force', $argv, true)) {
        fwrite(STDERR, "Ditolak: --fresh menghapus database dan wajib disertai --force.\n");
        exit(1);
    }
}

function elips_install_lock_write(): void
{
    $dir = dirname(elips_install_lock_path());
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    file_put_contents(
        elips_install_lock_path(),
        json_encode(['installed_at' => gmdate('c'), 'env' => elips_config()['env'] ?? 'development']) . "\n"
    );
}
