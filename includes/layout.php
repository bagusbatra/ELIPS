<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - includes/layout.php
// Komponen halaman. Setiap halaman mendefinisikan $page lalu
// memanggil partial() di tempat komponen diperlukan:
//
//   $page = ['title' => 'Kelola Kursus', 'scripts' => ['@crud', 'ui']];
//   partial('head'); partial('navbar/app'); partial('sidebar/admin');
//   ... isi halaman ...
//   partial('scripts');
//
// Layout (lebih ringkas): halaman cukup mengisi konten.
//
//   $page = ['title' => 'Kelola Kursus', 'roles' => ['admin'], 'sidebar' => 'admin', 'scripts' => ['@crud', 'ui']];
//   layout('app', $page);   // layouts/app.php (dashboard) atau layouts/page.php (umum)
//   (tutup PHP, tulis HTML <main class="dashboard-main">...</main>, buka PHP lagi)
//   end_layout();
//
// Kunci $page: title, description, area, body_class, head (opsi),
// scripts (daftar berkas JS setelah bundel inti), active (override menu).
// ============================================================

/**
 * Mulai halaman berlayout: jalankan guard akses (sebelum output), lalu tampung
 * isi halaman sampai end_layout() memasangnya ke layouts/<nama>.php.
 */
function layout(string $name, array $config): void
{
    global $page, $elips_layout_name;
    $page = $config;
    elips_guard($page);
    $elips_layout_name = $name;
    ob_start();
}

function end_layout(): void
{
    global $page, $elips_layout_name;
    $content = (string) ob_get_clean();
    require ELIPS_ROOT . '/layouts/' . $elips_layout_name . '.php';
}

/** Muat komponen dari folder partials/. $page otomatis tersedia di dalamnya. */
function partial(string $name, array $vars = []): void
{
    global $page;
    $page = is_array($page ?? null) ? $page : [];
    extract($vars, EXTR_SKIP);
    require ELIPS_ROOT . '/partials/' . $name . '.php';
}

/** Konfigurasi aplikasi (api/config.php) tanpa membuka koneksi database. */
function elips_app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require ELIPS_ROOT . '/api/config.php';
    }
    return $config;
}

/** Mode demo lokal: aplikasi berjalan tanpa server (localStorage + seed.js). */
function elips_local_demo(): bool
{
    return !empty(elips_app_config()['local_demo']);
}

/** Skrip inti (urutan penting). seed.js hanya dimuat pada mode demo lokal. */
function elips_core_scripts(): array
{
    return ['config', 'core', 'api', 'utils', 'data', 'sync', 'progress', 'auth', 'audit', 'notifications'];
}

/** Paket modul CRUD standar. Gunakan '@crud' di daftar scripts halaman. */
function elips_crud_scripts(): array
{
    return ['crud-users', 'crud-courses', 'crud-enrollments', 'crud-content', 'crud-grades'];
}

/**
 * Nama bundel (tanpa .js) bila bundel aktif dan lebih baru dari semua sumbernya,
 * selain itu null -> pakai berkas satuan.
 */
function elips_bundle_for(string $bundle, array $sources): ?string
{
    if (empty(elips_app_config()['bundle']) || elips_local_demo()) {
        return null;
    }
    $file = ELIPS_ROOT . '/assets/js/dist/' . $bundle . '.js';
    if (!is_file($file)) {
        return null;
    }
    $built = filemtime($file);
    foreach ($sources as $name) {
        $src = ELIPS_ROOT . '/assets/js/' . $name . '.js';
        if (is_file($src) && filemtime($src) > $built) {
            return null;
        }
    }
    return 'dist/' . $bundle;
}

/** Daftar akhir berkas JS halaman: inti + modul halaman ('@crud' diperluas). */
function elips_script_list(array $page): array
{
    $core = elips_core_scripts();
    if (elips_local_demo()) {
        array_splice($core, 5, 0, ['seed']); // posisi asli: setelah data, sebelum sync
    }
    $bundle = elips_bundle_for('core.bundle', elips_core_scripts());
    $list = $bundle !== null ? [$bundle] : $core;
    foreach (($page['scripts'] ?? []) as $name) {
        if ($name === '@crud') {
            $crud = elips_bundle_for('crud.bundle', elips_crud_scripts());
            $list = array_merge($list, $crud !== null ? [$crud] : elips_crud_scripts());
        } else {
            $list[] = $name;
        }
    }
    if (!empty($page['page_script'])) {
        $list[] = 'pages/' . $page['page_script'];
    }
    return $list;
}
