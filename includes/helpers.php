<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - includes/helpers.php
// Helper kecil untuk template.
// ============================================================

/** Escape HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absolut-dari-root untuk halaman/berkas proyek: url('admin/users.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * URL aset statis dengan versi (filemtime) agar browser/PWA selalu
 * memuat berkas terbaru setelah diubah: asset('css/style.css').
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ELIPS_ROOT . '/assets/' . $path;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . $path) . $version;
}

/** Path halaman yang sedang dibuka relatif terhadap folder proyek: 'admin/users.php'. */
function current_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $path = BASE_URL !== '' && strpos($script, BASE_URL . '/') === 0
        ? substr($script, strlen(BASE_URL))
        : $script;
    return ltrim($path, '/');
}

/** true bila $path (mis. 'admin/users.php') adalah halaman yang sedang dibuka. */
function is_current(string $path): bool
{
    global $page;
    $active = is_array($page ?? null) ? ($page['active'] ?? null) : null;
    return ltrim($path, '/') === ($active ?? current_path());
}

/** ' active' bila menu menuju halaman ini; dipakai di atribut class. */
function nav_active(string $path): string
{
    return is_current($path) ? ' active' : '';
}
