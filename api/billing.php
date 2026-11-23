<?php
// Direktori: api/billing.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - tagihan, pembayaran, verifikasi & nomor siswa
//
// - Satu tagihan (invoice) per pendaftaran/program siswa.
// - Siswa mengunggah bukti bayar (status 'pending'); admin atau
//   karyawan divisi Keuangan memverifikasi (setujui / ubah nominal /
//   tolak dengan alasan) atau mencatat pembayaran langsung.
// - Pembayaran tidak pernah dihapus: hanya dibatalkan (void) dengan
//   alasan dan tercatat di audit.
// - Status tagihan dihitung dari pembayaran terverifikasi.
// - Nomor siswa EC-xxxxxx dibuat saat tagihan pertama kali lunas:
//   berurutan, atomik di transaksi database, tidak dipakai ulang,
//   dimiliki oleh program (tagihan), bukan oleh siswa.
// ============================================================

require_once __DIR__ . '/migrations.php';

const ELIPS_PROOF_MAX_BYTES = 2097152;

function elips_payment_methods(): array
{
    return ['transfer', 'tunai', 'e-wallet', 'lainnya'];
}

/** Akses Keuangan: admin atau karyawan divisi keuangan. */
function elips_require_finance_access(): array
{
    $user = elips_require_login();
    if ($user['role'] === 'admin') {
        return $user;
    }
    if ($user['role'] !== 'karyawan' || ($user['division'] ?? '') !== 'keuangan') {
        throw new ElipsError('Anda tidak berhak mengakses data pembayaran.');
    }
    return $user;
}

/** Jalankan $fn dalam transaksi (ikut transaksi yang sudah berjalan bila ada). */
function elips_tx(callable $fn)
{
    $db = elips_db();
    if ($db->inTransaction()) {
        return $fn();
    }
    $db->beginTransaction();
    try {
        $result = $fn();
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

// ---------- Pendaftaran siswa ke kelas ----------

/** @return array{id:int,error:string} */
function elips_enroll_student_in_class(int $studentId, int $classId, int $courseId): array
{
    $db = elips_db();
    $stmt = $db->prepare(
        "SELECT id FROM enrollments
         WHERE student_id = ? AND (course_id = ? OR class_id = ?) AND status IN ('active', 'pending', 'completed') LIMIT 1"
    );
    $stmt->execute([$studentId, $courseId, $classId]);
    if ($stmt->fetchColumn()) {
        return ['id' => 0, 'error' => 'Siswa sudah terdaftar pada program ini.'];
    }
    // Baris lama yang dihentikan pada kelas yang sama menghalangi unique (student_id, class_id).
    $stmt = $db->prepare('SELECT id FROM enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
    $stmt->execute([$studentId, $classId]);
    $old = $stmt->fetchColumn();
    if ($old) {
        $db->prepare("UPDATE enrollments SET status = 'active', enrolled_by = 'registration', enrolled_at = ? WHERE id = ?")
            ->execute([gmdate('Y-m-d'), (int) $old]);
        return ['id' => (int) $old, 'error' => ''];
    }
    $db->prepare(
        "INSERT INTO enrollments (student_id, course_id, class_id, enrolled_by, enrolled_at, status, created_at)
         VALUES (?, ?, ?, 'registration', ?, 'active', UTC_TIMESTAMP())"
    )->execute([$studentId, $courseId, $classId, gmdate('Y-m-d')]);
    return ['id' => (int) $db->lastInsertId(), 'error' => ''];
}

// ---------- Tagihan ----------

function elips_invoice_create(array $d): int
{
    $db = elips_db();
    $amount = round((float) $d['amount_due'], 2);
    $db->prepare(
        "INSERT INTO invoices (registration_id, student_id, enrollment_id, course_id, class_id, course_title, class_name,
                               amount_due, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', UTC_TIMESTAMP())"
    )->execute([
        $d['registration_id'] ?? null, (int) $d['student_id'], $d['enrollment_id'] ?? null, (int) $d['course_id'],
        $d['class_id'] ?? null, (string) $d['course_title'], (string) $d['class_name'], $amount,
    ]);
    $id = (int) $db->lastInsertId();
    if ($amount <= 0) {
        // Program gratis: langsung lunas dan bernomor.
        elips_invoice_recalc($id);
    }
    return $id;
}

/** Nomor siswa berikutnya (EC-000001, ...). Wajib dipanggil dalam transaksi. */
function elips_assign_student_number(int $invoiceId): void
{
    $db = elips_db();
    $stmt = $db->prepare('SELECT student_number FROM invoices WHERE id = ? FOR UPDATE');
    $stmt->execute([$invoiceId]);
    $current = $stmt->fetchColumn();
    if ($current !== false && $current !== null && $current !== '') {
        return;
    }
    $seq = (int) $db->query("SELECT value FROM counters WHERE name = 'student_number' FOR UPDATE")->fetchColumn();
    $seq++;
    $db->prepare("UPDATE counters SET value = ? WHERE name = 'student_number'")->execute([$seq]);
    $db->prepare('UPDATE invoices SET student_number = ?, number_seq = ? WHERE id = ?')
        ->execute([sprintf('EC-%06d', $seq), $seq, $invoiceId]);
}

/** Hitung ulang status tagihan dari pembayaran terverifikasi. Mengunci baris tagihan. */
function elips_invoice_recalc(int $invoiceId): array
{
    return elips_tx(static function () use ($invoiceId): array {
        $db = elips_db();
        $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? FOR UPDATE');
        $stmt->execute([$invoiceId]);
        $inv = $stmt->fetch();
        if (!$inv) {
            throw new ElipsError('Tagihan tidak ditemukan.');
        }
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'verified'");
        $stmt->execute([$invoiceId]);
        $paid = round((float) $stmt->fetchColumn(), 2);
        $due = round((float) $inv['amount_due'], 2);

        $status = $paid >= $due ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        if ($status === 'paid') {
            $db->prepare('UPDATE invoices SET status = ?, paid_at = COALESCE(paid_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$status, $invoiceId]);
            elips_assign_student_number($invoiceId);
        } else {
            $db->prepare('UPDATE invoices SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$status, $invoiceId]);
        }
        return ['paid' => $paid, 'due' => $due, 'status' => $status];
    });
}

function elips_invoice_select_sql(): string
{
    return "SELECT i.*, u.name AS student_name, u.email AS student_email, u.phone AS student_phone, u.phone_norm AS student_phone_norm,
                   COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id AND p.status = 'verified'), 0) AS paid_total,
                   (SELECT COUNT(*) FROM payments p WHERE p.invoice_id = i.id AND p.status = 'pending') AS pending_count
            FROM invoices i
            JOIN users u ON u.id = i.student_id";
}

function elips_invoice_public(array $r): array
{
    $due = (float) $r['amount_due'];
    $paid = (float) $r['paid_total'];
    return [
        'id' => (int) $r['id'],
        'studentId' => (int) $r['student_id'],
        'studentName' => (string) $r['student_name'],
        'studentEmail' => (string) $r['student_email'],
        'studentPhone' => (string) $r['student_phone'],
        'courseId' => (int) $r['course_id'],
        'courseTitle' => (string) $r['course_title'],
        'className' => (string) $r['class_name'],
        'amountDue' => $due,
        'amountPaid' => $paid,
        'remaining' => max(0.0, round($due - $paid, 2)),
        'status' => (string) $r['status'],
        'pendingCount' => (int) $r['pending_count'],
        'studentNumber' => (string) ($r['student_number'] ?? ''),
        'paidAt' => elips_iso($r['paid_at'] ?? null),
        'createdAt' => elips_iso($r['created_at']),
    ];
}

function elips_payment_public(array $r, bool $finance): array
{
    $out = [
        'id' => (int) $r['id'],
        'invoiceId' => (int) $r['invoice_id'],
        'amount' => (float) $r['amount'],
        'method' => (string) $r['method'],
        'paidDate' => $r['paid_date'] === null ? '' : elips_date_only($r['paid_date']),
        'note' => (string) $r['note'],
        'status' => (string) $r['status'],
        'source' => (string) $r['source'],
        'hasProof' => !empty($r['proof_file']),
        'rejectReason' => (string) $r['reject_reason'],
        'voidReason' => (string) $r['void_reason'],
        'verifiedAt' => elips_iso($r['verified_at'] ?? null),
        'createdAt' => elips_iso($r['created_at']),
    ];
    if ($finance) {
        $out['verifiedBy'] = (string) ($r['verified_by_name'] ?? '');
        $out['submittedBy'] = (string) ($r['submitted_by_name'] ?? '');
    }
    return $out;
}

function elips_invoice_payments(int $invoiceId, bool $finance): array
{
    $stmt = elips_db()->prepare(
        'SELECT p.*, vb.name AS verified_by_name, sb.name AS submitted_by_name
         FROM payments p
         LEFT JOIN users vb ON vb.id = p.verified_by
         LEFT JOIN users sb ON sb.id = p.submitted_by
         WHERE p.invoice_id = ? ORDER BY p.created_at DESC, p.id DESC'
    );
    $stmt->execute([$invoiceId]);
    return array_map(static function (array $r) use ($finance): array {
        return elips_payment_public($r, $finance);
    }, $stmt->fetchAll());
}

function elips_invoice_fetch(int $id): ?array
{
    $stmt = elips_db()->prepare(elips_invoice_select_sql() . ' WHERE i.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ---------- Pengaturan rekening ----------

function elips_payment_settings(): array
{
    $default = ['bankName' => '', 'accountNumber' => '', 'accountHolder' => '', 'note' => ''];
    $stmt = elips_db()->prepare("SELECT value FROM app_settings WHERE name = 'payment_bank' LIMIT 1");
    $stmt->execute();
    $raw = $stmt->fetchColumn();
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? array_merge($default, array_intersect_key($decoded, $default)) : $default;
}

function elips_action_payment_settings_save(array $p): array
{
    $actor = elips_require_admin();
    elips_migrate_activation_schema();
    $clean = [];
    foreach (['bankName' => 80, 'accountNumber' => 40, 'accountHolder' => 100, 'note' => 300] as $k => $max) {
        $v = trim((string) ($p[$k] ?? ''));
        if (mb_strlen($v) > $max) {
            return elips_fail('Isian terlalu panjang (' . $k . ', maksimal ' . $max . ' karakter).');
        }
        $clean[$k] = $v;
    }
    elips_db()->prepare(
        "INSERT INTO app_settings (name, value, updated_at) VALUES ('payment_bank', ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()"
    )->execute([json_encode($clean, JSON_UNESCAPED_UNICODE)]);
    elips_audit('update', 'payment_settings', null, 'Rekening pembayaran', 'Memperbarui informasi rekening pembayaran.', $actor);
    return elips_ok('Informasi rekening disimpan.', $clean);
}

function elips_action_payment_settings_get(array $p): array
{
    elips_require_finance_access();
    elips_migrate_activation_schema();
    return elips_ok('', elips_payment_settings());
}

// ---------- Aksi siswa ----------

function elips_action_payment_mine(array $p): array
{
    $user = elips_require_login();
    if ($user['role'] !== 'peserta') {
        return elips_fail('Hanya untuk siswa.');
    }
    elips_migrate_activation_schema();
    $stmt = elips_db()->prepare(elips_invoice_select_sql() . ' WHERE i.student_id = ? ORDER BY i.created_at DESC, i.id DESC');
    $stmt->execute([(int) $user['id']]);
    $items = [];
    $locked = [];
    foreach ($stmt->fetchAll() as $row) {
        $inv = elips_invoice_public($row);
        $inv['payments'] = elips_invoice_payments((int) $row['id'], false);
        $items[] = $inv;
        if ($inv['status'] !== 'paid') {
            $locked[] = $inv['courseId'];
        }
    }
    return elips_ok('', [
        'invoices' => $items,
        'bank' => elips_payment_settings(),
        'methods' => elips_payment_methods(),
        'certificateLockedCourseIds' => array_values(array_unique($locked)),
    ]);
}

/** Simpan bukti bayar dari base64. @return array{file:string,mime:string} */
function elips_payment_store_proof(array $proof): array
{
    $data = (string) ($proof['data'] ?? '');
    if (($pos = strpos($data, ',')) !== false && stripos(substr($data, 0, $pos), 'base64') !== false) {
        $data = substr($data, $pos + 1);
    }
    if ($data === '') {
        throw new ElipsError('Bukti pembayaran wajib diunggah (JPG, PNG, atau PDF).');
    }
    if (strlen($data) > (int) (ELIPS_PROOF_MAX_BYTES * 1.4)) {
        throw new ElipsError('Ukuran bukti pembayaran maksimal 2 MB.');
    }
    $bin = base64_decode($data, true);
    if ($bin === false || $bin === '') {
        throw new ElipsError('Berkas bukti pembayaran tidak valid.');
    }
    if (strlen($bin) > ELIPS_PROOF_MAX_BYTES) {
        throw new ElipsError('Ukuran bukti pembayaran maksimal 2 MB.');
    }
    // Jenis berkas ditentukan dari isi, bukan dari nama/tipe kiriman klien.
    if (strncmp($bin, "\xFF\xD8\xFF", 3) === 0) {
        $mime = 'image/jpeg';
        $ext = 'jpg';
    } elseif (strncmp($bin, "\x89PNG\r\n\x1a\n", 8) === 0) {
        $mime = 'image/png';
        $ext = 'png';
    } elseif (strncmp($bin, '%PDF-', 5) === 0) {
        $mime = 'application/pdf';
        $ext = 'pdf';
    } else {
        throw new ElipsError('Bukti pembayaran harus berupa JPG, PNG, atau PDF.');
    }

    $dir = dirname(__DIR__) . '/storage/payment-proofs';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new ElipsError('Folder penyimpanan bukti pembayaran tidak dapat dibuat.');
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $name, $bin) === false) {
        throw new ElipsError('Bukti pembayaran gagal disimpan.');
    }
    return ['file' => $name, 'mime' => $mime];
}

function elips_payment_parse_amount($raw): float
{
    if (is_string($raw)) {
        $raw = str_replace(['.', ' '], '', $raw);
        $raw = str_replace(',', '.', $raw);
    }
    if (!is_numeric($raw)) {
        return 0.0;
    }
    return round((float) $raw, 2);
}

function elips_action_payment_submit(array $p): array
{
    $user = elips_require_login();
    if ($user['role'] !== 'peserta') {
        return elips_fail('Hanya siswa yang dapat mengirim bukti pembayaran.');
    }
    elips_migrate_activation_schema();

    $invoiceId = (int) ($p['invoiceId'] ?? 0);
    $amount = elips_payment_parse_amount($p['amount'] ?? 0);
    $method = (string) ($p['method'] ?? 'transfer');
    $note = trim((string) ($p['note'] ?? ''));
    $paidDate = elips_normalize_date($p['paidDate'] ?? '');

    if (!in_array($method, elips_payment_methods(), true)) return elips_fail('Metode pembayaran tidak valid.');
    if ($amount <= 0) return elips_fail('Nominal pembayaran harus lebih dari 0.');
    if (!$paidDate) return elips_fail('Tanggal pembayaran tidak valid.');
    if (strtotime($paidDate . ' 00:00:00') > time() + 86400) return elips_fail('Tanggal pembayaran tidak boleh di masa depan.');
    if (mb_strlen($note) > 500) return elips_fail('Catatan maksimal 500 karakter.');

    $limitKey = (string) $user['id'];
    if (elips_rate_limit_blocked('payment_submit', $limitKey, 10, 600) > 0) {
        return elips_fail('Terlalu banyak pengiriman bukti. Coba lagi beberapa menit lagi.');
    }

    $stored = null;
    try {
        $paymentId = elips_tx(static function () use ($user, $invoiceId, $amount, $method, $note, $paidDate, $p, &$stored): int {
            $db = elips_db();
            $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? AND student_id = ? FOR UPDATE');
            $stmt->execute([$invoiceId, (int) $user['id']]);
            $inv = $stmt->fetch();
            if (!$inv) throw new ElipsError('Tagihan tidak ditemukan.');
            if ($inv['status'] === 'paid') throw new ElipsError('Tagihan ini sudah lunas.');

            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'verified'");
            $stmt->execute([$invoiceId]);
            $remaining = round((float) $inv['amount_due'] - (float) $stmt->fetchColumn(), 2);
            if ($amount > $remaining) {
                throw new ElipsError('Nominal melebihi sisa tagihan (maksimal ' . number_format($remaining, 0, ',', '.') . ').');
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id = ? AND status = 'pending'");
            $stmt->execute([$invoiceId]);
            if ((int) $stmt->fetchColumn() >= 5) {
                throw new ElipsError('Masih ada beberapa bukti yang menunggu verifikasi. Mohon tunggu admin memprosesnya.');
            }

            $stored = elips_payment_store_proof(is_array($p['proof'] ?? null) ? $p['proof'] : []);
            $db->prepare(
                "INSERT INTO payments (invoice_id, amount, method, paid_date, note, proof_file, proof_mime, status, source, submitted_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'student', ?, UTC_TIMESTAMP())"
            )->execute([$invoiceId, $amount, $method, $paidDate, $note, $stored['file'], $stored['mime'], (int) $user['id']]);
            return (int) $db->lastInsertId();
        });
    } catch (Throwable $e) {
        if ($stored) {
            @unlink(dirname(__DIR__) . '/storage/payment-proofs/' . $stored['file']);
        }
        throw $e;
    }

    elips_rate_limit_hit('payment_submit', $limitKey, 600);
    elips_audit('create', 'payment', $paymentId, 'Tagihan #' . $invoiceId, 'Siswa mengirim bukti pembayaran Rp ' . number_format($amount, 0, ',', '.') . '.', $user);
    return elips_ok('Bukti pembayaran terkirim. Admin akan memverifikasinya.', ['id' => $paymentId]);
}

// ---------- Aksi Keuangan ----------

function elips_action_payment_invoices(array $p): array
{
    elips_require_finance_access();
    elips_migrate_activation_schema();

    $where = [];
    $params = [];
    $status = (string) ($p['status'] ?? '');
    if (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
        $where[] = 'i.status = ?';
        $params[] = $status;
    } elseif ($status === 'open') {
        $where[] = "i.status <> 'paid'";
    } elseif ($status === 'pending') {
        $where[] = "EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id AND p.status = 'pending')";
    }
    $q = trim((string) ($p['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR i.student_number LIKE ? OR i.course_title LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql = elips_invoice_select_sql() . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY (pending_count > 0) DESC, i.created_at DESC, i.id DESC LIMIT 500';
    $stmt = elips_db()->prepare($sql);
    $stmt->execute($params);
    return elips_ok('', array_map('elips_invoice_public', $stmt->fetchAll()));
}

function elips_action_payment_invoice(array $p): array
{
    elips_require_finance_access();
    elips_migrate_activation_schema();
    $row = elips_invoice_fetch((int) ($p['invoiceId'] ?? 0));
    if (!$row) {
        return elips_fail('Tagihan tidak ditemukan.');
    }
    $inv = elips_invoice_public($row);
    $inv['payments'] = elips_invoice_payments((int) $row['id'], true);
    $inv['methods'] = elips_payment_methods();
    return elips_ok('', $inv);
}

/** Nilai sisa tagihan (terkunci). */
function elips_invoice_lock_remaining(int $invoiceId): array
{
    $db = elips_db();
    $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? FOR UPDATE');
    $stmt->execute([$invoiceId]);
    $inv = $stmt->fetch();
    if (!$inv) {
        throw new ElipsError('Tagihan tidak ditemukan.');
    }
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'verified'");
    $stmt->execute([$invoiceId]);
    $remaining = round((float) $inv['amount_due'] - (float) $stmt->fetchColumn(), 2);
    return [$inv, $remaining];
}

function elips_payment_insert_verified(int $invoiceId, float $amount, string $method, string $paidDate, string $note, array $actor): int
{
    $db = elips_db();
    $db->prepare(
        "INSERT INTO payments (invoice_id, amount, method, paid_date, note, status, source, submitted_by, verified_by, verified_at, created_at)
         VALUES (?, ?, ?, ?, ?, 'verified', 'admin', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
    )->execute([$invoiceId, $amount, $method, $paidDate, $note, (int) $actor['id'], (int) $actor['id']]);
    return (int) $db->lastInsertId();
}

function elips_payment_common_fields(array $p): array
{
    $method = (string) ($p['method'] ?? 'transfer');
    if (!in_array($method, elips_payment_methods(), true)) {
        throw new ElipsError('Metode pembayaran tidak valid.');
    }
    $paidDate = elips_normalize_date($p['paidDate'] ?? '') ?: gmdate('Y-m-d');
    $note = trim((string) ($p['note'] ?? ''));
    if (mb_strlen($note) > 500) {
        throw new ElipsError('Catatan maksimal 500 karakter.');
    }
    return [$method, $paidDate, $note];
}

/** Catat pembayaran (sebagian/penuh) langsung oleh admin/Keuangan. */
function elips_action_payment_record(array $p): array
{
    $actor = elips_require_finance_access();
    elips_migrate_activation_schema();
    $invoiceId = (int) ($p['invoiceId'] ?? 0);
    $amount = elips_payment_parse_amount($p['amount'] ?? 0);
    if ($amount <= 0) return elips_fail('Nominal pembayaran harus lebih dari 0.');
    [$method, $paidDate, $note] = elips_payment_common_fields($p);

    $result = elips_tx(static function () use ($invoiceId, $amount, $method, $paidDate, $note, $actor): array {
        [$inv, $remaining] = elips_invoice_lock_remaining($invoiceId);
        if ($remaining <= 0) throw new ElipsError('Tagihan ini sudah lunas.');
        if ($amount > $remaining) {
            throw new ElipsError('Nominal melebihi sisa tagihan (maksimal ' . number_format($remaining, 0, ',', '.') . '). Gunakan tombol Lunas Pembayaran untuk melunasi sisanya.');
        }
        $id = elips_payment_insert_verified($invoiceId, $amount, $method, $paidDate, $note, $actor);
        return ['id' => $id, 'calc' => elips_invoice_recalc($invoiceId), 'inv' => $inv];
    });

    elips_audit('create', 'payment', $result['id'], (string) $result['inv']['course_title'],
        'Mencatat pembayaran Rp ' . number_format($amount, 0, ',', '.') . ' (' . $method . ') untuk tagihan #' . $invoiceId . '.', $actor);
    return elips_ok($result['calc']['status'] === 'paid' ? 'Pembayaran dicatat. Tagihan LUNAS.' : 'Pembayaran sebagian dicatat.', elips_payment_invoice_payload($invoiceId));
}

/** Tombol "Lunas Pembayaran": catat sisa tagihan sebagai pembayaran terverifikasi. */
function elips_action_payment_mark_paid(array $p): array
{
    $actor = elips_require_finance_access();
    elips_migrate_activation_schema();
    $invoiceId = (int) ($p['invoiceId'] ?? 0);
    [$method, $paidDate, $note] = elips_payment_common_fields($p);

    $result = elips_tx(static function () use ($invoiceId, $method, $paidDate, $note, $actor): array {
        [$inv, $remaining] = elips_invoice_lock_remaining($invoiceId);
        if ($remaining <= 0) throw new ElipsError('Tagihan ini sudah lunas.');
        $id = elips_payment_insert_verified($invoiceId, $remaining, $method, $paidDate, $note !== '' ? $note : 'Pelunasan', $actor);
        return ['id' => $id, 'amount' => $remaining, 'calc' => elips_invoice_recalc($invoiceId), 'inv' => $inv];
    });

    elips_audit('update', 'invoice', $invoiceId, (string) $result['inv']['course_title'],
        'Melunasi tagihan #' . $invoiceId . ' (sisa Rp ' . number_format($result['amount'], 0, ',', '.') . ').', $actor);
    return elips_ok('Tagihan dilunasi.', elips_payment_invoice_payload($invoiceId));
}

/** Verifikasi bukti dari siswa: setujui (nominal boleh disesuaikan) atau tolak dengan alasan. */
function elips_action_payment_verify(array $p): array
{
    $actor = elips_require_finance_access();
    elips_migrate_activation_schema();
    $paymentId = (int) ($p['paymentId'] ?? 0);
    $decision = (string) ($p['decision'] ?? '');
    if (!in_array($decision, ['approve', 'reject'], true)) return elips_fail('Keputusan tidak valid.');
    $reason = trim((string) ($p['reason'] ?? ''));
    if ($decision === 'reject' && $reason === '') return elips_fail('Alasan penolakan wajib diisi.');
    if (mb_strlen($reason) > 500) return elips_fail('Alasan maksimal 500 karakter.');

    $stmt = elips_db()->prepare('SELECT invoice_id FROM payments WHERE id = ? LIMIT 1');
    $stmt->execute([$paymentId]);
    $invoiceId = (int) $stmt->fetchColumn();
    if ($invoiceId <= 0) return elips_fail('Pembayaran tidak ditemukan.');

    $summary = elips_tx(static function () use ($paymentId, $invoiceId, $decision, $reason, $p, $actor): string {
        [$inv, $remaining] = elips_invoice_lock_remaining($invoiceId);
        $db = elips_db();
        $stmt = $db->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $pay = $stmt->fetch();
        if (!$pay || $pay['status'] !== 'pending') {
            throw new ElipsError('Pembayaran ini sudah diproses.');
        }
        if ($decision === 'reject') {
            $db->prepare("UPDATE payments SET status = 'rejected', reject_reason = ?, verified_by = ?, verified_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$reason, (int) $actor['id'], $paymentId]);
            return 'Menolak bukti pembayaran #' . $paymentId . ' (' . $reason . ').';
        }
        $amount = array_key_exists('amount', $p) && $p['amount'] !== '' && $p['amount'] !== null
            ? elips_payment_parse_amount($p['amount'])
            : (float) $pay['amount'];
        if ($amount <= 0) throw new ElipsError('Nominal harus lebih dari 0.');
        if ($amount > $remaining) {
            throw new ElipsError('Nominal melebihi sisa tagihan (maksimal ' . number_format($remaining, 0, ',', '.') . ').');
        }
        $note = (string) $pay['note'];
        if (round($amount, 2) !== round((float) $pay['amount'], 2)) {
            $note = trim($note . ' [Nominal disesuaikan dari Rp ' . number_format((float) $pay['amount'], 0, ',', '.') . ']');
        }
        $db->prepare("UPDATE payments SET status = 'verified', amount = ?, note = ?, verified_by = ?, verified_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([$amount, mb_substr($note, 0, 500), (int) $actor['id'], $paymentId]);
        elips_invoice_recalc($invoiceId);
        return 'Menyetujui bukti pembayaran #' . $paymentId . ' sebesar Rp ' . number_format($amount, 0, ',', '.') . '.';
    });

    elips_audit($decision === 'approve' ? 'approve' : 'reject', 'payment', $paymentId, 'Tagihan #' . $invoiceId, $summary, $actor);
    return elips_ok($decision === 'approve' ? 'Pembayaran disetujui.' : 'Pembayaran ditolak.', elips_payment_invoice_payload($invoiceId));
}

/** Batalkan pembayaran terverifikasi (tidak dihapus; alasan wajib). */
function elips_action_payment_void(array $p): array
{
    $actor = elips_require_finance_access();
    elips_migrate_activation_schema();
    $paymentId = (int) ($p['paymentId'] ?? 0);
    $reason = trim((string) ($p['reason'] ?? ''));
    if ($reason === '') return elips_fail('Alasan pembatalan wajib diisi.');
    if (mb_strlen($reason) > 500) return elips_fail('Alasan maksimal 500 karakter.');

    $stmt = elips_db()->prepare('SELECT invoice_id FROM payments WHERE id = ? LIMIT 1');
    $stmt->execute([$paymentId]);
    $invoiceId = (int) $stmt->fetchColumn();
    if ($invoiceId <= 0) return elips_fail('Pembayaran tidak ditemukan.');

    $amount = elips_tx(static function () use ($paymentId, $invoiceId, $reason, $actor): float {
        elips_invoice_lock_remaining($invoiceId);
        $db = elips_db();
        $stmt = $db->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $pay = $stmt->fetch();
        if (!$pay || $pay['status'] !== 'verified') {
            throw new ElipsError('Hanya pembayaran terverifikasi yang dapat dibatalkan.');
        }
        $db->prepare("UPDATE payments SET status = 'void', void_reason = ?, voided_by = ?, voided_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([$reason, (int) $actor['id'], $paymentId]);
        elips_invoice_recalc($invoiceId);
        return (float) $pay['amount'];
    });

    elips_audit('void', 'payment', $paymentId, 'Tagihan #' . $invoiceId,
        'Membatalkan pembayaran Rp ' . number_format($amount, 0, ',', '.') . ' (' . $reason . ').', $actor);
    return elips_ok('Pembayaran dibatalkan.', elips_payment_invoice_payload($invoiceId));
}

function elips_payment_invoice_payload(int $invoiceId): array
{
    $row = elips_invoice_fetch($invoiceId);
    $inv = $row ? elips_invoice_public($row) : [];
    if ($row) {
        $inv['payments'] = elips_invoice_payments($invoiceId, true);
        $inv['methods'] = elips_payment_methods();
    }
    return $inv;
}

// ---------- Unduh bukti ----------

/** GET ?action=payment.proof&id=N — hanya pemilik tagihan atau Keuangan/admin. */
function elips_payment_proof_send(int $paymentId): void
{
    $user = elips_session_user();
    $deny = static function (int $code): void {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $code === 404 ? 'Berkas tidak ditemukan.' : 'Tidak diizinkan.';
        exit;
    };
    if (!$user || $user['status'] !== 'aktif') {
        $deny(403);
    }
    elips_migrate_activation_schema();
    $stmt = elips_db()->prepare(
        'SELECT p.proof_file, p.proof_mime, i.student_id FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$paymentId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['proof_file'])) {
        $deny(404);
    }
    $isFinance = $user['role'] === 'admin' || ($user['role'] === 'karyawan' && ($user['division'] ?? '') === 'keuangan');
    $isOwner = $user['role'] === 'peserta' && (int) $user['id'] === (int) $row['student_id'];
    if (!$isFinance && !$isOwner) {
        $deny(403);
    }
    $path = dirname(__DIR__) . '/storage/payment-proofs/' . basename((string) $row['proof_file']);
    if (!is_file($path)) {
        $deny(404);
    }
    header('Content-Type: ' . $row['proof_mime']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="bukti-' . $paymentId . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

// ---------- Sertifikat ----------

/** Pesan penghalang bila siswa belum lunas untuk program ini, atau '' bila boleh. */
function elips_certificate_payment_block(int $studentId, int $courseId): string
{
    elips_migrate_activation_schema();
    $stmt = elips_db()->prepare(
        "SELECT i.amount_due, COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id AND p.status = 'verified'), 0) AS paid
         FROM invoices i WHERE i.student_id = ? AND i.course_id = ? AND i.status <> 'paid' LIMIT 1"
    );
    $stmt->execute([$studentId, $courseId]);
    $row = $stmt->fetch();
    if (!$row) {
        return '';
    }
    $left = max(0, (float) $row['amount_due'] - (float) $row['paid']);
    return 'Pembayaran siswa belum lunas (sisa Rp ' . number_format($left, 0, ',', '.') . '). Sertifikat baru dapat diterbitkan setelah lunas.';
}

// ---------- Badge ----------

/**
 * Angka badge sidebar. Merah = pendaftar baru; kuning = siswa aktif yang
 * tagihannya belum lunas; resetRequests = permintaan reset sandi.
 */
function elips_badge_counts(?array $user): array
{
    if (!$user || ($user['status'] ?? '') !== 'aktif') {
        return [];
    }
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $isFinance = ($user['role'] ?? '') === 'karyawan' && ($user['division'] ?? '') === 'keuangan';
    if (!$isAdmin && !$isFinance) {
        return [];
    }
    elips_migrate_activation_schema();
    $db = elips_db();
    $counts = [];
    if ($isAdmin) {
        $counts['registrationsNew'] = (int) $db->query("SELECT COUNT(*) FROM course_registrations WHERE status = 'baru'")->fetchColumn();
        $counts['resetRequests'] = (int) $db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status IN ('pending', 'sent')")->fetchColumn();
    }
    $counts['unpaidInvoices'] = (int) $db->query(
        "SELECT COUNT(*) FROM invoices i JOIN users u ON u.id = i.student_id
         WHERE i.status <> 'paid' AND u.status = 'aktif'"
    )->fetchColumn();
    return $counts;
}