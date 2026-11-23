<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - includes/bootstrap.php
// Dimuat di baris pertama setiap halaman .php.
// Menyediakan BASE_URL, helper URL/aset, dan fungsi komponen.
// ============================================================

define('ELIPS_ROOT', dirname(__DIR__));

/**
 * Path web proyek dari document root, mis. '' (Laragon virtual host
 * http://elips.test) atau '/ELIPS' (http://localhost/ELIPS).
 * Dihitung dari SCRIPT_NAME dikurangi lokasi skrip relatif terhadap
 * folder proyek, sehingga tidak bergantung pada nama folder.
 * Dapat dipaksa lewat environment variable ELIPS_BASE_URL.
 */
function elips_detect_base_url(): string
{
    $forced = getenv('ELIPS_BASE_URL');
    if ($forced !== false && $forced !== '') {
        return '/' . trim($forced, '/') === '/' ? '' : '/' . trim($forced, '/');
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = str_replace('\\', '/', ELIPS_ROOT);

    if ($scriptFile !== '' && stripos($scriptFile, $root . '/') === 0) {
        $relative = '/' . substr($scriptFile, strlen($root) + 1);
        if ($relative !== '' && substr($scriptName, -strlen($relative)) === $relative) {
            return rtrim(substr($scriptName, 0, strlen($scriptName) - strlen($relative)), '/');
        }
    }

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']) : '';
    if ($docRoot !== '' && stripos($root, $docRoot) === 0) {
        return rtrim(substr($root, strlen($docRoot)), '/');
    }
    return '';
}

define('BASE_URL', elips_detect_base_url());

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/auth.php';
