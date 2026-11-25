<?php
// Direktori: api/class_lifecycle.php
// ============================================================
// ELIPS LMS - Siklus status angkatan (otomatis)
//
// Hanya 3 status yang dilihat admin:
//   Pendaftaran (open)    : belum masuk tanggal mulai pembelajaran
//   Berlangsung (ongoing) : tanggal mulai sudah tiba & belum ditutup admin
//   Selesai (completed)   : hanya lewat "Tutup pembelajaran" oleh admin/operasional
//
// Status Pendaftaran <-> Berlangsung dihitung otomatis dari tanggal mulai
// (elips_sync_class_status, dipanggil di api/index.php pada tiap permintaan).
// Tanggal selesai hanya PERKIRAAN: tanggal sesi terakhir pada jadwal.
// Aksi: class.close, class.reopen
// ============================================================

/** Tanggal hari ini (zona waktu operasional). */
function elips_class_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(elips_attendance_timezone())))->format('Y-m-d');
}

/** Kolom penutupan angkatan (idempotent, dibuat sekali per proses). */
function elips_ensure_class_close_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db = elips_db();
    $has = $db->query("SHOW COLUMNS FROM `classes` LIKE 'completed_at'")->fetch();
    if (!$has) {
        $db->exec('ALTER TABLE `classes` ADD COLUMN `completed_at` DATETIME NULL DEFAULT NULL, ADD COLUMN `completed_by` INT UNSIGNED NULL DEFAULT NULL, ADD COLUMN `completion_note` VARCHAR(500) NOT NULL DEFAULT \'\'');
    }
    $done = true;
}

/**
 * Samakan status & perkiraan tanggal selesai dengan tanggal hari ini.
 * Murah dan aman dijalankan berulang; kegagalan tidak boleh menghentikan permintaan.
 */
function elips_sync_class_status(): void
{
    try {
        elips_ensure_class_close_schema();
        $db = elips_db();
        $today = elips_class_today();

        // Tanggal mulai tiba -> Berlangsung.
        $db->prepare(
            "UPDATE classes SET status = 'ongoing', updated_at = UTC_TIMESTAMP()
             WHERE status IN ('draft', 'open', 'ready') AND period_start IS NOT NULL AND period_start <= ?"
        )->execute([$today]);

        // Draft/Siap lama tanpa tanggal mulai atau belum waktunya -> Pendaftaran.
        $db->prepare(
            "UPDATE classes SET status = 'open', updated_at = UTC_TIMESTAMP()
             WHERE status IN ('draft', 'ready') AND (period_start IS NULL OR period_start > ?)"
        )->execute([$today]);

        // Tanggal mulai digeser ke depan: kembali ke Pendaftaran selama belum ada sesi terkonfirmasi.
        $db->prepare(
            "UPDATE classes c SET c.status = 'open', c.updated_at = UTC_TIMESTAMP()
             WHERE c.status = 'ongoing' AND c.period_start > ?
               AND NOT EXISTS (SELECT 1 FROM class_sessions s WHERE s.class_id = c.id AND s.status IN ('confirmed', 'completed'))"
        )->execute([$today]);

        // Perkiraan selesai = tanggal sesi terakhir (selama belum ditutup).
        $db->exec(
            "UPDATE classes c
             JOIN (SELECT class_id, MAX(session_date) AS last_date FROM class_sessions
                   WHERE status NOT IN ('rescheduled', 'cancelled') GROUP BY class_id) x ON x.class_id = c.id
             SET c.period_end = x.last_date
             WHERE c.status IN ('open', 'ongoing') AND (c.period_end IS NULL OR c.period_end <> x.last_date)"
        );
    } catch (Throwable $e) {
        error_log('[ELIPS] sync status angkatan gagal: ' . $e->getMessage());
    }
}

/** Peringatan sebelum angkatan ditutup (tidak memblokir, hanya meminta konfirmasi). */
function elips_class_close_warnings(int $classId): array
{
    $db = elips_db();
    $warnings = [];

    $stmt = $db->prepare("SELECT COUNT(*) FROM class_sessions WHERE class_id = ? AND status = 'scheduled'");
    $stmt->execute([$classId]);
    $open = (int) $stmt->fetchColumn();
    if ($open > 0) {
        $warnings[] = $open . ' sesi belum dikonfirmasi.';
    }

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM enrollments e JOIN classes c ON c.id = e.class_id
         WHERE e.class_id = ? AND e.status IN ('active', 'pending')
           AND (SELECT COUNT(*) FROM class_lesson_progress p JOIN lessons l ON l.id = p.lesson_id
                WHERE p.enrollment_id = e.id AND l.curriculum_version_id = c.curriculum_version_id)
               < (SELECT COUNT(*) FROM lessons l2 WHERE l2.curriculum_version_id = c.curriculum_version_id)"
    );
    $stmt->execute([$classId]);
    $behind = (int) $stmt->fetchColumn();
    if ($behind > 0) {
        $warnings[] = $behind . ' peserta belum semua materinya dikonfirmasi (tentor baru bisa mengisi nilai akhir peserta yang materinya lengkap).';
    }
    return $warnings;
}

function elips_action_class_close(array $p): array
{
    $actor = elips_require_academic_access();
    elips_ensure_class_close_schema();
    $id = (int) ($p['id'] ?? 0);
    $class = elips_class_row($id);
    if (!$class) {
        return elips_fail('Angkatan tidak ditemukan.');
    }
    if ($class['status'] !== 'ongoing') {
        return elips_fail($class['status'] === 'completed'
            ? 'Angkatan ini sudah ditutup.'
            : 'Hanya angkatan yang sedang Berlangsung yang dapat ditutup.');
    }

    $warnings = elips_class_close_warnings($id);
    if ($warnings && empty($p['confirm'])) {
        return [
            'ok' => false,
            'message' => 'Ada hal yang belum selesai: ' . implode(' ', $warnings),
            'data' => ['needsConfirm' => true, 'warnings' => $warnings],
        ];
    }

    $db = elips_db();
    $db->prepare(
        "UPDATE classes SET status = 'completed', completed_at = UTC_TIMESTAMP(), completed_by = ?, completion_note = ?,
                updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?"
    )->execute([(int) $actor['id'], mb_substr(implode(' ', $warnings), 0, 500), (int) $actor['id'], $id]);

    elips_audit(
        'status',
        'class',
        $id,
        $class['name'],
        'Menutup pembelajaran angkatan "' . $class['name'] . '"' . ($warnings ? ' (dengan peringatan: ' . implode(' ', $warnings) . ')' : '') . '.',
        $actor
    );

    return elips_ok('Pembelajaran angkatan "' . $class['name'] . '" ditutup.', elips_class_public($id));
}

function elips_action_class_reopen(array $p): array
{
    $actor = elips_require_academic_access();
    elips_ensure_class_close_schema();
    $id = (int) ($p['id'] ?? 0);
    $class = elips_class_row($id);
    if (!$class) {
        return elips_fail('Angkatan tidak ditemukan.');
    }
    if ($class['status'] !== 'completed') {
        return elips_fail('Hanya angkatan yang sudah Selesai yang dapat dibuka kembali.');
    }
    $reason = trim((string) ($p['reason'] ?? ''));
    if ($reason === '') {
        return elips_fail('Alasan membuka kembali wajib diisi.');
    }

    elips_db()->prepare(
        "UPDATE classes SET status = 'ongoing', completed_at = NULL, completed_by = NULL, completion_note = '',
                updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?"
    )->execute([(int) $actor['id'], $id]);
    elips_sync_class_status();

    elips_audit(
        'status',
        'class',
        $id,
        $class['name'],
        'Membuka kembali pembelajaran angkatan "' . $class['name'] . '". Alasan: ' . $reason,
        $actor
    );

    return elips_ok('Angkatan "' . $class['name'] . '" dibuka kembali.', elips_class_public($id));
}