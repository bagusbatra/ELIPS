<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - seed data Marketing
// Jalankan: php api/seed-marketing-leads.php
// Membuat lead, follow-up, dan target bulan berjalan untuk enam
// akun karyawan Marketing dari api/seed-marketing.php.
// Skrip idempoten: lead dicocokkan lewat owner + email, follow-up
// hanya dibuat bila lead belum memiliki catatan, target di-upsert
// berdasarkan user + periode.
// ============================================================

require __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$timezone = new DateTimeZone('Asia/Jakarta');
$now = new DateTimeImmutable('now', $timezone);
$today = $now->format('Y-m-d');
$period = $now->format('Y-m');

$accounts = [
    'andi.marketing@example.com',
    'bella.marketing@example.com',
    'candra.marketing@example.com',
    'dinda.marketing@example.com',
    'fajar.marketing@example.com',
    'gita.marketing@example.com',
];

// owner email, nama campaign, kanal, status, mulai (hari), selesai (hari),
// budget, target lead, target pendaftaran, catatan
$campaignSeeds = [
    ['andi.marketing@example.com', 'Promo Kelas Malam November', 'sosial_media', 'aktif', -10, 20, 1500000, 20, 5, 'Iklan berbayar dan konten organik untuk kelas malam.'],
    ['andi.marketing@example.com', 'Referral Alumni', 'komunitas', 'selesai', -40, -5, 500000, 10, 3, 'Program referral bersama alumni angkatan sebelumnya.'],
    ['bella.marketing@example.com', 'Campaign Sosial Media Bella', 'sosial_media', 'aktif', -8, 22, 2000000, 18, 4, 'Konten testimoni dan promosi kelas bahasa Jepang.'],
    ['candra.marketing@example.com', 'Kerja Sama Sekolah Candra', 'sekolah', 'aktif', -15, 15, 1000000, 16, 4, 'Presentasi program ke sekolah mitra di Garut.'],
    ['candra.marketing@example.com', 'Telemarketing Candra', 'telemarketing', 'batal', -20, -2, 750000, 12, 3, 'Penawaran telepon dihentikan karena respons rendah.'],
    ['dinda.marketing@example.com', 'Open House Bandung', 'pameran', 'aktif', -5, 10, 3000000, 20, 5, 'Open house dan trial class di cabang Bandung.'],
    ['fajar.marketing@example.com', 'Campaign Digital Fajar', 'email', 'draft', 0, 30, 1200000, 18, 4, 'Rencana email nurturing untuk prospek baru.'],
    ['fajar.marketing@example.com', 'Kerja Sama Sekolah Fajar', 'sekolah', 'aktif', -12, 18, 900000, 15, 4, 'Kunjungan sekolah dan distribusi brosur digital.'],
    ['gita.marketing@example.com', 'Pameran Pendidikan Gita', 'pameran', 'selesai', -35, -2, 2500000, 16, 4, 'Booth pameran pendidikan dan pengumpulan lead.'],
    ['gita.marketing@example.com', 'Telemarketing Korporat Gita', 'telemarketing', 'aktif', -7, 23, 1800000, 18, 4, 'Penawaran program korporat untuk tim perusahaan.'],
];

// owner email, nama, telepon, email lead, kota, sumber, status, minat,
// catatan, jadwal (today|overdue|future|none), nilai pendaftaran, campaign
$leadSeeds = [
    ['andi.marketing@example.com', 'Budi Santoso', '081298761001', 'budi.lead@example.com', 'Bandung', 'walk_in', 'baru', 'Bahasa Inggris Dewasa', 'Datang langsung ke cabang, minta info kelas malam.', 'today', 0, 'Promo Kelas Malam November'],
    ['andi.marketing@example.com', 'Siti Aminah', '081298761002', 'siti.lead@example.com', 'Cimahi', 'referensi', 'prospek', 'IELTS Preparation', 'Direkomendasikan alumni, butuh jadwal akhir pekan.', 'overdue', 0, 'Referral Alumni'],
    ['bella.marketing@example.com', 'Rina Pratama', '081298761003', 'rina.lead@example.com', 'Bandung', 'sosial_media', 'dihubungi', 'Bahasa Jepang Basic', 'Tertarik kelas online, menanyakan biaya cicilan.', 'today', 0, 'Campaign Sosial Media Bella'],
    ['bella.marketing@example.com', 'Agus Salim', '081298761004', 'agus.lead@example.com', 'Sumedang', 'pameran', 'terdaftar', 'Bahasa Inggris Anak', 'Mendaftar setelah trial, pembayaran lunas.', 'none', 2500000, 'Campaign Sosial Media Bella'],
    ['candra.marketing@example.com', 'Dewi Lestari', '081298761005', 'dewi.lead@example.com', 'Garut', 'sekolah', 'baru', 'Bahasa Mandarin Basic', 'Kontak dari kerja sama sekolah, perlu follow-up awal.', 'future', 0, 'Kerja Sama Sekolah Candra'],
    ['candra.marketing@example.com', 'Hendra Gunawan', '081298761006', 'hendra.lead@example.com', 'Bandung', 'telemarketing', 'batal', 'TOEFL Preparation', 'Menunda karena jadwal kerja padat.', 'none', 0, 'Telemarketing Candra'],
    ['dinda.marketing@example.com', 'Maya Sari', '081298761007', 'maya.lead@example.com', 'Bandung', 'walk_in', 'prospek', 'Bahasa Korea Basic', 'Sudah trial satu sesi, menimbang jadwal.', 'overdue', 0, 'Open House Bandung'],
    ['dinda.marketing@example.com', 'Rizky Maulana', '081298761008', 'rizky.lead@example.com', 'Bandung', 'referensi', 'terdaftar', 'Bahasa Inggris Dewasa', 'Daftar kelas malam, pembayaran transfer.', 'none', 3200000, 'Open House Bandung'],
    ['fajar.marketing@example.com', 'Nabila Putri', '081298761009', 'nabila.lead@example.com', 'Cimahi', 'sosial_media', 'dihubungi', 'Bahasa Jepang JLPT', 'Menanyakan sertifikat dan durasi program.', 'today', 0, 'Campaign Digital Fajar'],
    ['fajar.marketing@example.com', 'Yudi Hartono', '081298761010', 'yudi.lead@example.com', 'Bandung', 'sekolah', 'baru', 'Bahasa Inggris Dewasa', 'Lead dari pameran sekolah, minta brosur digital.', 'future', 0, 'Kerja Sama Sekolah Fajar'],
    ['gita.marketing@example.com', 'Lestari Wulandari', '081298761011', 'lestari.lead@example.com', 'Bandung', 'pameran', 'prospek', 'Bahasa Mandarin Bisnis', 'Tertarik program korporat untuk tim kecil.', 'today', 0, 'Pameran Pendidikan Gita'],
    ['gita.marketing@example.com', 'Fahmi Idris', '081298761012', 'fahmi.lead@example.com', 'Bandung', 'telemarketing', 'terdaftar', 'TOEFL Preparation', 'Daftar paket intensif dua bulan.', 'none', 2800000, 'Telemarketing Korporat Gita'],
];

$targetSeeds = [
    ['andi.marketing@example.com', 20, 60, 5, 12500000],
    ['bella.marketing@example.com', 18, 55, 4, 10000000],
    ['candra.marketing@example.com', 16, 50, 4, 10000000],
    ['dinda.marketing@example.com', 20, 60, 5, 12500000],
    ['fajar.marketing@example.com', 18, 55, 4, 10000000],
    ['gita.marketing@example.com', 16, 50, 4, 10000000],
];

try {
    $db = elips_db();

    $findUser = $db->prepare('SELECT id, name, role, division FROM users WHERE email = ? LIMIT 1');
    $users = [];
    foreach ($accounts as $email) {
        $findUser->execute([$email]);
        $user = $findUser->fetch();
        if (!$user) {
            fwrite(STDERR, "Akun {$email} tidak ditemukan. Jalankan php api/seed-marketing.php terlebih dahulu.\n");
            exit(1);
        }
        if ($user['role'] !== 'karyawan' || $user['division'] !== 'marketing') {
            fwrite(STDERR, "Akun {$email} bukan karyawan divisi Marketing.\n");
            exit(1);
        }
        $users[$email] = $user;
    }

    $scheduleDate = function (string $mode) use ($today): ?string {
        $base = new DateTimeImmutable($today, new DateTimeZone('Asia/Jakarta'));
        if ($mode === 'today') return $today;
        if ($mode === 'overdue') return $base->modify('-2 days')->format('Y-m-d');
        if ($mode === 'future') return $base->modify('+2 days')->format('Y-m-d');
        return null;
    };

    $convertedAt = $now->format('Y-m') . '-04 05:00:00';

    $findCampaign = $db->prepare('SELECT id FROM marketing_campaigns WHERE owner_id = ? AND name = ? LIMIT 1');
    $insertCampaign = $db->prepare(
        'INSERT INTO marketing_campaigns
            (owner_id, name, channel, status, start_date, end_date, budget,
             target_leads, target_enrollments, notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $updateCampaign = $db->prepare(
        'UPDATE marketing_campaigns
         SET channel = ?, status = ?, start_date = ?, end_date = ?, budget = ?,
             target_leads = ?, target_enrollments = ?, notes = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $campaignIds = [];
    $campaignCount = 0;
    $baseDate = new DateTimeImmutable($today, $timezone);
    foreach ($campaignSeeds as $seed) {
        [$ownerEmail, $name, $channel, $status, $startOffset, $endOffset, $budget, $targetLeads, $targetEnrollments, $notes] = $seed;
        $owner = $users[$ownerEmail];
        $startDate = $baseDate->modify(($startOffset >= 0 ? '+' : '') . $startOffset . ' days')->format('Y-m-d');
        $endDate = $baseDate->modify(($endOffset >= 0 ? '+' : '') . $endOffset . ' days')->format('Y-m-d');

        $findCampaign->execute([(int) $owner['id'], $name]);
        $campaignId = $findCampaign->fetchColumn();
        if ($campaignId === false || $campaignId === null) {
            $insertCampaign->execute([
                (int) $owner['id'], $name, $channel, $status, $startDate, $endDate, $budget,
                $targetLeads, $targetEnrollments, $notes,
            ]);
            $campaignId = (int) $db->lastInsertId();
            echo "Campaign dibuat: {$name} ({$owner['name']})\n";
        } else {
            $campaignId = (int) $campaignId;
            $updateCampaign->execute([
                $channel, $status, $startDate, $endDate, $budget,
                $targetLeads, $targetEnrollments, $notes, $campaignId,
            ]);
            echo "Campaign diperbarui: {$name} ({$owner['name']})\n";
        }
        $campaignIds[$ownerEmail][$name] = $campaignId;
        $campaignCount++;
    }

    $findLead = $db->prepare('SELECT id FROM marketing_leads WHERE owner_id = ? AND email = ? LIMIT 1');
    $insertLead = $db->prepare(
        'INSERT INTO marketing_leads
            (owner_id, campaign_id, name, phone, email, city, source, status, interest, notes,
             next_followup_date, converted_at, enrollment_value, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $updateLead = $db->prepare(
        'UPDATE marketing_leads
         SET campaign_id = ?, name = ?, phone = ?, city = ?, source = ?, status = ?, interest = ?, notes = ?,
             next_followup_date = ?, converted_at = ?, enrollment_value = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $countFollowups = $db->prepare('SELECT COUNT(*) FROM marketing_followups WHERE lead_id = ?');
    $insertFollowup = $db->prepare(
        'INSERT INTO marketing_followups
            (lead_id, owner_id, channel, followup_date, note, outcome, next_date, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );

    $createdLeads = 0;
    $updatedLeads = 0;
    $createdFollowups = 0;

    $db->beginTransaction();
    foreach ($leadSeeds as $seed) {
        [$ownerEmail, $name, $phone, $leadEmail, $city, $source, $status, $interest, $notes, $schedule, $value, $campaignName] = $seed;
        $owner = $users[$ownerEmail];
        $nextDate = $scheduleDate($schedule);
        $converted = $status === 'terdaftar' ? $convertedAt : null;
        $leadCreatedAt = $now->modify('-10 days')->format('Y-m-d H:i:s');
        $campaignId = $campaignIds[$ownerEmail][$campaignName] ?? null;

        $findLead->execute([(int) $owner['id'], $leadEmail]);
        $leadId = $findLead->fetchColumn();
        if ($leadId === false || $leadId === null) {
            $insertLead->execute([
                (int) $owner['id'], $campaignId, $name, $phone, $leadEmail, $city, $source, $status, $interest,
                $notes, $nextDate, $converted, $value, $leadCreatedAt,
            ]);
            $leadId = (int) $db->lastInsertId();
            $createdLeads++;
            echo "Lead dibuat: {$name} ({$owner['name']})\n";
        } else {
            $leadId = (int) $leadId;
            $updateLead->execute([
                $campaignId, $name, $phone, $city, $source, $status, $interest, $notes,
                $nextDate, $converted, $value, $leadId,
            ]);
            $updatedLeads++;
            echo "Lead diperbarui: {$name} ({$owner['name']})\n";
        }

        $countFollowups->execute([$leadId]);
        if ((int) $countFollowups->fetchColumn() === 0) {
            $followupDate = (new DateTimeImmutable($today, $timezone))->modify('-3 days')->format('Y-m-d');
            $channel = $status === 'terdaftar' ? 'whatsapp' : ($source === 'walk_in' ? 'kunjungan' : 'telepon');
            if ($status === 'terdaftar') {
                $followupNote = 'Konfirmasi pendaftaran dan pembayaran.';
                $outcome = 'Terdaftar';
                $followupNext = null;
            } elseif ($status === 'batal') {
                $followupNote = 'Konfirmasi ulang, lead menunda pendaftaran.';
                $outcome = 'Tidak jadi';
                $followupNext = null;
            } else {
                $followupNote = 'Follow-up awal: ' . $notes;
                $outcome = 'Bersedia dihubungi lagi';
                $followupNext = $nextDate;
            }
            $insertFollowup->execute([
                $leadId, (int) $owner['id'], $channel, $followupDate,
                $followupNote, $outcome, $followupNext,
            ]);
            $createdFollowups++;
        }
    }

    $findTarget = $db->prepare('SELECT id FROM marketing_targets WHERE user_id = ? AND period = ? LIMIT 1');
    $insertTarget = $db->prepare(
        'INSERT INTO marketing_targets
            (user_id, period, leads_target, followups_target, enrollments_target, revenue_target, created_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $updateTarget = $db->prepare(
        'UPDATE marketing_targets
         SET leads_target = ?, followups_target = ?, enrollments_target = ?, revenue_target = ?,
             updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $targetCount = 0;
    foreach ($targetSeeds as $seed) {
        [$ownerEmail, $leadsTarget, $followupsTarget, $enrollmentsTarget, $revenueTarget] = $seed;
        $owner = $users[$ownerEmail];
        $findTarget->execute([(int) $owner['id'], $period]);
        $targetId = $findTarget->fetchColumn();
        if ($targetId === false || $targetId === null) {
            $insertTarget->execute([
                (int) $owner['id'], $period, $leadsTarget, $followupsTarget, $enrollmentsTarget, $revenueTarget,
            ]);
        } else {
            $updateTarget->execute([
                $leadsTarget, $followupsTarget, $enrollmentsTarget, $revenueTarget, (int) $targetId,
            ]);
        }
        $targetCount++;
    }

    $db->commit();

    echo "\nSelesai: {$campaignCount} campaign disimpan, {$createdLeads} lead dibuat, "
        . "{$updatedLeads} lead diperbarui, {$createdFollowups} follow-up dibuat, "
        . "{$targetCount} target disimpan untuk periode {$period}.\n";
    echo "Tanggal acuan follow-up: {$today} (Asia/Jakarta).\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Gagal membuat data Marketing dummy: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
