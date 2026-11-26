<?php
// Direktori: api/index.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - front controller API
// Semua permintaan: JSON { action, payload } atau GET ?action=...
// ============================================================

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/snapshot.php';
require __DIR__ . '/actions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$payload = [];

if ($method === 'POST') {
    $body = elips_request_body();
    if (isset($body['action'])) {
        $action = (string) $body['action'];
    }
    if (isset($body['payload']) && is_array($body['payload'])) {
        $payload = $body['payload'];
    } elseif ($body && !isset($body['action'])) {
        $payload = $body;
    }
}

try {
    if ($action === 'bootstrap' || $action === 'ping') {
        elips_json_response([
            'ok' => true,
            'message' => '',
            'data' => null,
            'authenticated' => elips_session_user() !== null,
            'session' => elips_session_payload(),
            'collections' => elips_snapshot(),
        ]);
    }

    // Angka badge sidebar: ringan, tanpa snapshot.
    if ($action === 'badges') {
        elips_json_response([
            'ok' => true,
            'message' => '',
            'data' => elips_badge_counts(elips_session_user()),
        ]);
    }

    // Bukti pembayaran: berkas, bukan JSON.
    if ($action === 'tutor.document') {
        elips_tutor_document_send((int) ($_GET['id'] ?? 0));
    }
    if ($action === 'certificate.file') {
        elips_certificate_file_send((int) ($_GET['id'] ?? 0), (string) ($_GET['format'] ?? 'pdf'), (string) ($_GET['side'] ?? 'front'));
    }
    if ($action === 'payment.proof') {
        elips_payment_proof_send((int) ($_GET['id'] ?? 0));
    }

    elips_sync_class_status();
    $result = elips_dispatch($action, $payload);
    // Aksi baca-saja / publik / keuangan tidak perlu snapshot penuh
    // (data pembayaran & biodata sengaja tidak ikut snapshot).
    $noSnapshot = [
        'course.classes', 'course.register', 'course.registration.list',
        'activation.info', 'password.forgot', 'password.requests', 'password.reset.issue',
        'password.request.dismiss', 'password.reset.info', 'password.reset.complete',
        'payment.mine', 'payment.submit', 'payment.invoices', 'payment.invoice', 'payment.record',
        'tutor.sendActivation', 'tutor.profile', 'tutor.activationInfo', 'tutor.document.upload',
        'tutor.document.remove', 'tutor.activationComplete',
        'cert.queue', 'cert.issue', 'cert.mine', 'cert.verify',
        'payment.markPaid', 'payment.verify', 'payment.void', 'payment.settings.get', 'payment.settings.save',
    ];
    // Portal tentor (tp.*) dan halaman publikasi (cp.*) memuat datanya sendiri per halaman, tanpa snapshot.
    if ($result['ok'] && !in_array($action, $noSnapshot, true) && strpos($action, 'tp.') !== 0 && strpos($action, 'cp.') !== 0) {
        // Setiap perubahan mengembalikan snapshot terbaru sehingga cache
        // client selalu identik dengan database.
        $result['snapshot'] = elips_snapshot();
    }
    $result['session'] = elips_session_payload();
    elips_json_response($result);
} catch (ElipsError $e) {
    elips_json_response([
        'ok' => false,
        'message' => $e->getMessage(),
        'data' => null,
        'session' => elips_session_payload(),
    ]);
} catch (Throwable $e) {
    error_log('[ELIPS] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    elips_json_response([
        'ok' => false,
        'message' => 'Terjadi kesalahan pada server. Periksa koneksi database.',
        'data' => null,
        'session' => elips_session_payload(),
    ]);
}