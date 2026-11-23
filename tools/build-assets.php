<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - bangun bundel JS
//   php tools/build-assets.php
// Menggabungkan skrip inti & paket CRUD menjadi dua berkas
// (assets/js/dist/core.bundle.js dan crud.bundle.js) sehingga tiap halaman
// memuat 2 permintaan, bukan 15+. Bila `terser` terpasang (npm i -g terser),
// hasilnya diminify. seed.js sengaja TIDAK ikut (khusus mode demo lokal).
// Bila sumber lebih baru daripada bundel, halaman otomatis memuat berkas
// satuan, jadi mengedit JS tidak perlu build ulang saat development.
// ============================================================

require __DIR__ . '/../includes/layout.php';

$root = dirname(__DIR__);
$bundles = [
    'core.bundle.js' => elips_core_scripts(),
    'crud.bundle.js' => elips_crud_scripts(),
];
@mkdir($root . '/assets/js/dist', 0755, true);
$hasTerser = trim((string) shell_exec(DIRECTORY_SEPARATOR === '\\' ? 'where terser 2>NUL' : 'command -v terser 2>/dev/null')) !== '';

foreach ($bundles as $out => $names) {
    $code = '';
    foreach ($names as $name) {
        $file = $root . '/assets/js/' . $name . '.js';
        if (!is_file($file)) {
            fwrite(STDERR, "Berkas hilang: $file\n");
            exit(1);
        }
        $code .= "/* ---- $name.js ---- */\n" . rtrim((string) file_get_contents($file)) . "\n;\n";
    }
    $target = $root . '/assets/js/dist/' . $out;
    file_put_contents($target, $code);
    if ($hasTerser) {
        $cmd = 'terser ' . escapeshellarg($target) . ' --compress --mangle -o ' . escapeshellarg($target) . ' 2>&1';
        exec($cmd, $log, $rc);
        if ($rc !== 0) {
            fwrite(STDERR, "terser gagal untuk $out:\n" . implode("\n", $log) . "\n");
            exit(1);
        }
    }
    printf("%s: %d berkas -> %.1f KB%s\n", $out, count($names), filesize($target) / 1024, $hasTerser ? ' (minify)' : '');
}
