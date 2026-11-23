<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - includes/auth.php
// Guard akses di SERVER. Dipanggil dari partials/head.php sebelum
// ada output apa pun, sehingga halaman terlarang tidak pernah
// terkirim ke browser (bukan hanya dialihkan lewat JavaScript).
//
// Kunci $page yang dibaca:
//   roles     => ['admin']            role yang boleh membuka halaman
//   divisions => ['marketing']        (opsional) batasi divisi karyawan;
//                                     admin selalu lolos bila 'admin' ada di roles
// Tanpa 'roles' halaman bersifat publik.
// Matikan sementara (mis. debug tanpa DB): ELIPS_AUTH_GUARD=0
// ============================================================

/** Dashboard tujuan per role (dipakai saat role tidak cocok). */
function elips_dashboard_for_role(string $role): string
{
    switch ($role) {
        case 'admin': return 'admin/dashboard.php';
        case 'instruktur': return 'instructor/dashboard.php';
        case 'karyawan': return 'employee/shared/dashboard.php';
        default: return 'student/dashboard.php';
    }
}

/** Muat lapisan API & mulai sesi PHP yang sama dengan api/index.php. */
function elips_server_boot(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    require_once ELIPS_ROOT . '/api/db.php';
    require_once ELIPS_ROOT . '/api/helpers.php';
    require_once ELIPS_ROOT . '/api/snapshot.php';
    require_once ELIPS_ROOT . '/api/actions.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    $booted = true;
}

/**
 * Pengguna yang sedang login, atau null. Bila sesi PHP sudah habis tetapi
 * cookie "ingat saya" masih berlaku, sesi dipulihkan (token dirotasi)
 * persis seperti auth.resume pada API.
 */
function elips_current_user(): ?array
{
    elips_server_boot();
    $user = elips_session_user();
    if ($user) {
        return $user;
    }
    if (elips_remember_current_token() !== '') {
        $result = elips_action_auth_resume();
        if (!empty($result['ok'])) {
            return elips_session_user();
        }
    }
    return null;
}

function elips_redirect(string $path): void
{
    header('Location: ' . url($path), true, 302);
    exit;
}

function elips_forbidden_page(int $code, string $title, string $message): void
{
    http_response_code($code);
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' - ELIPS LMS</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem">'
        . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p><p><a href="' . e(url('login.php')) . '">Kembali ke halaman masuk</a></p></body></html>';
    exit;
}

/** Terapkan aturan akses halaman berdasarkan $page. */
function elips_guard(array $page): void
{
    $roles = $page['roles'] ?? null;
    if (!$roles || getenv('ELIPS_AUTH_GUARD') === '0') {
        return;
    }
    // Halaman terlindungi tidak boleh disimpan di cache bersama/back-forward.
    header('Cache-Control: no-store');

    try {
        $user = elips_current_user();
    } catch (Throwable $e) {
        error_log('[ELIPS guard] ' . $e->getMessage());
        elips_forbidden_page(503, 'Server belum siap', 'Koneksi database gagal. Periksa MySQL dan konfigurasi api/config.php.');
    }

    if (!$user) {
        elips_redirect('login.php');
    }
    $role = (string) $user['role'];
    if (!in_array($role, $roles, true)) {
        elips_redirect(elips_dashboard_for_role($role));
    }
    $divisions = $page['divisions'] ?? null;
    if ($divisions && $role === 'karyawan' && !in_array((string) ($user['division'] ?? ''), $divisions, true)) {
        elips_redirect(elips_dashboard_for_role($role));
    }
}
