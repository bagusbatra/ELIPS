<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - seed 14 surat menyurat dummy
// Jalankan: php api/seed-letters.php
// Skrip ini idempoten: surat dicocokkan lewat jenis + nomor +
// tanggal surat, sehingga aman dijalankan berulang tanpa duplikat.
// ============================================================

require __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$letters = [
    ['masuk', '421/DIKBUD/X/2026', 'Undangan Rapat Koordinasi Kurikulum', 'Dinas Pendidikan Kota', 'ELIPS LMS', '2026-10-05', '2026-10-06', 'Undangan', 'penting'],
    ['masuk', '118/PERS/IX/2026', 'Permohonan Kerja Sama Pelatihan', 'PT Nusantara Digital', 'ELIPS LMS', '2026-10-08', '2026-10-09', 'Permohonan', 'biasa'],
    ['masuk', '77/UNIV/X/2026', 'Pemberitahuan Kunjungan Studi Banding', 'Universitas Cendekia', 'ELIPS LMS', '2026-10-12', '2026-10-13', 'Pemberitahuan', 'biasa'],
    ['masuk', '005/KEU/X/2026', 'Laporan Realisasi Anggaran Triwulan III', 'Bagian Keuangan', 'ELIPS LMS', '2026-10-15', '2026-10-16', 'Laporan', 'penting'],
    ['masuk', '332/BKD/X/2026', 'Undangan Sosialisasi Jabatan Fungsional', 'Badan Kepegawaian Daerah', 'ELIPS LMS', '2026-10-20', '2026-10-21', 'Undangan', 'biasa'],
    ['masuk', '091/HRD/X/2026', 'Pemberitahuan Perpanjangan Kontrak Instruktur', 'PT Sumber Daya Mandiri', 'ELIPS LMS', '2026-10-26', '2026-10-27', 'Kepegawaian', 'segera'],
    ['masuk', '014/SEK/X/2026', 'Penyampaian Dokumen Akreditasi', 'Sekretariat Yayasan', 'ELIPS LMS', '2026-11-02', '2026-11-03', 'Umum', 'rahasia'],
    ['keluar', '045/ELIPS/X/2026', 'Balasan Undangan Rapat Koordinasi', 'ELIPS LMS', 'Dinas Pendidikan Kota', '2026-10-07', '2026-10-07', 'Undangan', 'penting'],
    ['keluar', '052/ELIPS/X/2026', 'Penawaran Program Pelatihan Digital', 'ELIPS LMS', 'PT Nusantara Digital', '2026-10-10', '2026-10-10', 'Permohonan', 'biasa'],
    ['keluar', '058/ELIPS/X/2026', 'Konfirmasi Jadwal Kunjungan Studi Banding', 'ELIPS LMS', 'Universitas Cendekia', '2026-10-14', '2026-10-14', 'Pemberitahuan', 'biasa'],
    ['keluar', '063/ELIPS/X/2026', 'Laporan Penyelenggaraan Pelatihan Oktober', 'ELIPS LMS', 'Kepala Dinas Pendidikan', '2026-10-18', '2026-10-19', 'Laporan', 'penting'],
    ['keluar', '071/ELIPS/X/2026', 'Permohonan Narasumber Sosialisasi', 'ELIPS LMS', 'Badan Kepegawaian Daerah', '2026-10-22', '2026-10-22', 'Permohonan', 'biasa'],
    ['keluar', '078/ELIPS/X/2026', 'Tanggapan Perpanjangan Kontrak Instruktur', 'ELIPS LMS', 'PT Sumber Daya Mandiri', '2026-10-29', '2026-10-30', 'Kepegawaian', 'segera'],
    ['keluar', '084/ELIPS/XI/2026', 'Pengiriman Dokumen Akreditasi', 'ELIPS LMS', 'Sekretariat Yayasan', '2026-11-04', '2026-11-04', 'Umum', 'rahasia'],
];

try {
    $db = elips_db();

    $admin = $db->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetch();
    if (!$admin) {
        throw new RuntimeException('Akun Admin tidak ditemukan. Jalankan php api/install.php terlebih dahulu.');
    }
    $adminId = (int) $admin['id'];

    $find = $db->prepare(
        'SELECT id FROM letters WHERE direction = ? AND letter_number = ? AND letter_date = ? LIMIT 1'
    );
    $insert = $db->prepare(
        'INSERT INTO letters
            (direction, letter_number, subject, sender, recipient, letter_date, handled_date,
             category, classification, status, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $update = $db->prepare(
        'UPDATE letters SET subject = ?, sender = ?, recipient = ?, handled_date = ?,
            category = ?, classification = ?
         WHERE id = ?'
    );

    $created = 0;
    $updated = 0;

    $db->beginTransaction();
    foreach ($letters as [$direction, $number, $subject, $sender, $recipient, $letterDate, $handledDate, $category, $classification]) {
        $find->execute([$direction, $number, $letterDate]);
        $existingId = $find->fetchColumn();
        if ($existingId !== false && $existingId !== null) {
            $update->execute([$subject, $sender, $recipient, $handledDate, $category, $classification, $existingId]);
            $updated++;
            echo "Diperbarui: {$direction} {$number} (id {$existingId})\n";
            continue;
        }

        $insert->execute([
            $direction, $number, $subject, $sender, $recipient, $letterDate, $handledDate,
            $category, $classification, 'baru', $adminId
        ]);
        $created++;
        echo "Dibuat: {$direction} {$number} (id {$db->lastInsertId()})\n";
    }
    $db->commit();

    $total = (int) $db->query('SELECT COUNT(*) FROM letters')->fetchColumn();
    echo "\nSelesai: {$created} surat dibuat, {$updated} surat diperbarui.\n";
    echo "Total surat di database: {$total}\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Gagal membuat surat dummy: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
