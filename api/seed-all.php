<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - seed lengkap seluruh tabel database (data dummy)
// Jalankan: php api/seed-all.php
// Atau buka di browser: /api/seed-all.php
// Skrip ini idempoten: data dicocokkan lewat kunci alami sehingga
// aman dijalankan berulang tanpa membuat duplikat.
// Disarankan dijalankan setelah php api/install.php.
// ============================================================

require __DIR__ . '/db.php';
require __DIR__ . '/setup-guard.php';
elips_setup_guard('seed');
require __DIR__ . '/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

$db = elips_db();
$config = elips_config();
$demoHash = password_hash((string) ($config['demo_password'] ?? 'elips123'), PASSWORD_DEFAULT);
$timezone = new DateTimeZone('Asia/Jakarta');
$now = new DateTimeImmutable('now', $timezone);
$today = $now->format('Y-m-d');
$currentPeriod = $now->format('Y-m');
$currentYear = $now->format('Y');
// Tanggal referensi tetap untuk baris yang kuncinya bergantung pada tanggal
// (absensi & shift). Memakai "today" akan menambah baris baru setiap hari.
$seedAnchor = new DateTimeImmutable('2026-11-17', $timezone);

$report = [];

function seed_out(string $message): void
{
    echo $message . "\n";
}

function seed_bump(string $table, int $count = 1): void
{
    global $report;
    $report[$table] = ($report[$table] ?? 0) + $count;
}

function seed_add_days(string $date, int $days): string
{
    $base = new DateTimeImmutable($date, new DateTimeZone('Asia/Jakarta'));
    return $base->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function seed_find(PDO $db, string $table, array $where): ?array
{
    $conditions = [];
    $params = [];
    foreach ($where as $column => $value) {
        if ($value === null) {
            $conditions[] = "`{$column}` IS NULL";
        } else {
            $conditions[] = "`{$column}` = ?";
            $params[] = $value;
        }
    }
    $stmt = $db->prepare(
        "SELECT * FROM `{$table}` WHERE " . implode(' AND ', $conditions) . ' LIMIT 1'
    );
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function seed_insert(PDO $db, string $table, array $data): int
{
    $columns = array_keys($data);
    $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . '`) VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $db->prepare($sql)->execute(array_values($data));
    return (int) $db->lastInsertId();
}

/**
 * Cari baris berdasarkan kunci alami; buat bila belum ada.
 * @return array{0:int,1:bool} [id, created]
 */
function seed_ensure(PDO $db, string $table, array $where, array $data): array
{
    $row = seed_find($db, $table, $where);
    if ($row !== null) {
        return [(int) $row['id'], false];
    }
    return [seed_insert($db, $table, $data), true];
}

/**
 * Tambahkan kolom bila database lama belum memilikinya (MySQL/MariaDB).
 */
function seed_ensure_column(PDO $db, string $table, string $column, string $definition): void
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }
    $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    seed_out("Kolom baru ditambahkan: {$table}.{$column}");
}

function seed_predicate_letter(PDO $db, float $score): string
{
    $stmt = $db->prepare(
        'SELECT letter FROM predicate_ranges
         WHERE is_active = 1 AND ? BETWEEN min_score AND max_score
         ORDER BY sort_order ASC, min_score DESC LIMIT 1'
    );
    $stmt->execute([$score]);
    $letter = $stmt->fetchColumn();
    return $letter === false ? 'E' : (string) $letter;
}

function seed_audit(PDO $db, string $action, string $entity, ?int $entityId, string $entityLabel, string $detail, int $actorId = 1): void
{
    $exists = seed_find($db, 'audit_logs', [
        'action' => $action,
        'entity' => $entity,
        'entity_id' => $entityId,
        'detail' => $detail,
    ]);
    if ($exists !== null) {
        return;
    }
    seed_insert($db, 'audit_logs', [
        'action' => $action,
        'entity' => $entity,
        'entity_id' => $entityId,
        'entity_label' => $entityLabel,
        'actor_id' => $actorId,
        'actor_name' => 'Administrator',
        'actor_role' => 'admin',
        'detail' => $detail,
        'created_at' => gmdate('Y-m-d H:i:s'),
    ]);
    seed_bump('audit_logs');
}

function seed_notification(PDO $db, array $entry): void
{
    $where = [
        'type' => $entry['type'],
        'recipient_id' => $entry['recipient_id'] ?? null,
        'entity' => $entry['entity'] ?? '',
        'entity_id' => $entry['entity_id'] ?? null,
        'subject' => $entry['subject'] ?? '',
    ];
    if (seed_find($db, 'notifications', $where) !== null) {
        return;
    }
    seed_insert($db, 'notifications', [
        'type' => $entry['type'],
        'channel' => 'email',
        'simulated' => 1,
        'status' => 'sent',
        'recipient_id' => $entry['recipient_id'] ?? null,
        'recipient_name' => $entry['recipient_name'] ?? '',
        'recipient_email' => $entry['recipient_email'] ?? '',
        'recipient_role' => $entry['recipient_role'] ?? 'peserta',
        'subject' => $entry['subject'] ?? '',
        'body' => $entry['body'] ?? '',
        'entity' => $entry['entity'] ?? '',
        'entity_id' => $entry['entity_id'] ?? null,
        'entity_label' => $entry['entity_label'] ?? '',
        'created_at' => gmdate('Y-m-d H:i:s'),
    ]);
    seed_bump('notifications');
}

// ---------- Pastikan tabel tambahan tersedia pada database lama ----------
$db->exec(
    "CREATE TABLE IF NOT EXISTS `marketing_tasks` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `owner_id` INT UNSIGNED NOT NULL,
        `campaign_id` INT UNSIGNED NULL,
        `lead_id` INT UNSIGNED NULL,
        `title` VARCHAR(150) NOT NULL,
        `description` VARCHAR(1000) NOT NULL DEFAULT '',
        `type` ENUM('follow_up', 'campaign', 'administrasi', 'lainnya') NOT NULL DEFAULT 'lainnya',
        `priority` ENUM('rendah', 'sedang', 'tinggi') NOT NULL DEFAULT 'sedang',
        `status` ENUM('belum', 'proses', 'selesai', 'batal') NOT NULL DEFAULT 'belum',
        `due_date` DATE NULL,
        `completed_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `idx_marketing_tasks_owner` (`owner_id`),
        KEY `idx_marketing_tasks_status` (`status`),
        KEY `idx_marketing_tasks_priority` (`priority`),
        KEY `idx_marketing_tasks_due` (`due_date`),
        KEY `idx_marketing_tasks_campaign` (`campaign_id`),
        KEY `idx_marketing_tasks_lead` (`lead_id`),
        CONSTRAINT `fk_marketing_tasks_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_marketing_tasks_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_marketing_tasks_lead` FOREIGN KEY (`lead_id`) REFERENCES `marketing_leads` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$db->exec(
    "CREATE TABLE IF NOT EXISTS `marketing_clients` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `owner_id` INT UNSIGNED NOT NULL,
        `campaign_id` INT UNSIGNED NULL,
        `name` VARCHAR(150) NOT NULL,
        `company` VARCHAR(150) NOT NULL DEFAULT '',
        `pic` VARCHAR(150) NOT NULL DEFAULT '',
        `phone` VARCHAR(30) NOT NULL DEFAULT '',
        `email` VARCHAR(190) NOT NULL DEFAULT '',
        `city` VARCHAR(100) NOT NULL DEFAULT '',
        `address` VARCHAR(255) NOT NULL DEFAULT '',
        `status` ENUM('prospek', 'aktif', 'nonaktif') NOT NULL DEFAULT 'prospek',
        `notes` VARCHAR(1000) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `idx_marketing_clients_owner` (`owner_id`),
        KEY `idx_marketing_clients_campaign` (`campaign_id`),
        KEY `idx_marketing_clients_status` (`status`),
        CONSTRAINT `fk_marketing_clients_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_marketing_clients_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

// DDL harus dijalankan sebelum transaksi: ALTER TABLE melakukan commit
// implisit pada MySQL dan akan membatalkan transaksi seeding.
// Kolom tampilan katalog dibaca ui.js dan landing.js dari snapshot.
seed_ensure_column($db, 'courses', 'location', "VARCHAR(150) NOT NULL DEFAULT ''");
seed_ensure_column($db, 'courses', 'start_date', 'DATE NULL');
seed_ensure_column($db, 'courses', 'meetings', 'INT UNSIGNED NOT NULL DEFAULT 0');
seed_ensure_column($db, 'courses', 'price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
seed_ensure_column($db, 'courses', 'discount_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
seed_ensure_column($db, 'courses', 'image', "VARCHAR(255) NOT NULL DEFAULT ''");

$db->beginTransaction();

try {
    // ============================================================
    // 1. KATEGORI
    // ============================================================
    $categorySeeds = [
        ['Pelatihan', 'pelatihan'],
        ['Soft Skill', 'soft-skill'],
        ['Administrasi', 'administrasi'],
        ['Reguler Class', 'reguler-class'],
        ['Bahasa', 'bahasa'],
        ['Teknologi', 'teknologi'],
        ['Keuangan', 'keuangan'],
        // Kategori kursus uji yang sudah ada di database (dipakai kursus ke-5).
        ['Uji', 'uji'],
        // Program unggulan pada index.html: TI & Desain Grafis.
        ['Desain Grafis', 'desain-grafis'],
    ];
    $categories = [];
    foreach ($categorySeeds as [$name, $slug]) {
        [$id, $created] = seed_ensure($db, 'categories', ['slug' => $slug], [
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);
        if ($created) {
            seed_bump('categories');
        }
        $categories[$slug] = $id;
    }
    seed_out('Kategori siap: ' . count($categories));

    // ============================================================
    // 2. CABANG
    // ============================================================
    $branchSeeds = [
        ['CBG-JKT', 'Cabang Jakarta', 'Jl. Jenderal Sudirman No. 21', 'Jakarta', '021-5550101', 'Dewi Lestari', 'Kantor pusat dan kelas reguler wilayah Jabodetabek.'],
        ['CBG-BDG', 'Cabang Bandung', 'Jl. Asia Afrika No. 88', 'Bandung', '022-5550202', 'Rangga Pratama', 'Cabang wilayah Bandung dan sekitarnya.'],
        ['CBG-SBY', 'Cabang Surabaya', 'Jl. Basuki Rahmat No. 45', 'Surabaya', '031-5550303', 'Maya Kusuma', 'Cabang wilayah Jawa Timur.'],
        ['CBG-YOG', 'Cabang Yogyakarta', 'Jl. Malioboro No. 17', 'Yogyakarta', '0274-5550404', 'Bagas Wicaksono', 'Cabang wilayah DI Yogyakarta.'],
        ['CBG-SMG', 'Cabang Semarang', 'Jl. Pandanaran No. 33', 'Semarang', '024-5550505', 'Ayu Pramesti', 'Cabang wilayah Jawa Tengah.'],
    ];
    $branches = [];
    foreach ($branchSeeds as [$code, $name, $address, $city, $phone, $manager, $notes]) {
        [$id, $created] = seed_ensure($db, 'branches', ['code' => $code], [
            'code' => $code,
            'name' => $name,
            'address' => $address,
            'city' => $city,
            'phone' => $phone,
            'manager' => $manager,
            'status' => 'active',
            'notes' => $notes,
        ]);
        if ($created) {
            seed_bump('branches');
        }
        $branches[$code] = $id;
    }
    seed_out('Cabang siap: ' . count($branches));

    // ============================================================
    // 3. PENGGUNA (admin, instruktur, peserta, karyawan)
    // ============================================================
    $userSeeds = [
        ['Administrator', 'admin@example.com', '081234567890', 'admin', '', '2026-01-01'],
        ['Ahmad Fauzi, M.M.', 'ahmad@example.com', '081234567891', 'instruktur', '', '2026-09-15'],
        ['Siti Nurhaliza, S.Psi.', 'siti@example.com', '081234567892', 'instruktur', '', '2026-09-16'],
        ['Budi Santoso, A.Md.', 'budi@example.com', '081234567893', 'instruktur', '', '2026-09-17'],
        ['Dian Paramita, S.Pd.', 'dian.instruktur@example.com', '081234567811', 'instruktur', '', '2026-09-18'],
        ['Rudi Hartono, M.Kom.', 'rudi.instruktur@example.com', '081234567812', 'instruktur', '', '2026-09-19'],
        ['Budi Santoso', 'budi.student@example.com', '081234567894', 'peserta', '', '2026-10-01'],
        ['Ani Nurhayati', 'ani@example.com', '081234567895', 'peserta', '', '2026-10-02'],
        ['Rina Setiawati', 'rina@example.com', '081234567896', 'peserta', '', '2026-10-03'],
        ['Dimas Prakoso', 'dimas@example.com', '081234567897', 'peserta', '', '2026-10-04'],
        ['Lina Marlina', 'lina@example.com', '081234567898', 'peserta', '', '2026-10-05'],
        ['Raka Aditya', 'raka@example.com', '081234567899', 'peserta', '', '2026-10-06'],
        ['Sari Dewi', 'sari@example.com', '081234567900', 'peserta', '', '2026-10-07'],
        ['Fajar Nugroho', 'fajar.nugroho@example.com', '081234567901', 'peserta', '', '2026-10-08'],
        ['Intan Permata', 'intan@example.com', '081234567902', 'peserta', '', '2026-10-09'],
        ['Bayu Wijaya', 'bayu.wijaya@example.com', '081234567903', 'peserta', '', '2026-10-10'],
        ['Nabila Rahma', 'nabila@example.com', '081234567904', 'peserta', '', '2026-10-11'],
        ['Yoga Pratama', 'yoga@example.com', '081234567905', 'peserta', '', '2026-10-12'],
        ['Dewi Lestari', 'dewi.karyawan@example.com', '081234567801', 'karyawan', 'marketing', '2026-10-05'],
        ['Agus Prasetyo', 'agus.karyawan@example.com', '081234567802', 'karyawan', 'operasional', '2026-10-06'],
        ['Sri Wahyuni', 'sri.karyawan@example.com', '081234567803', 'karyawan', 'keuangan', '2026-10-07'],
        ['Joko Susilo', 'joko.karyawan@example.com', '081234567804', 'karyawan', 'hrd', '2026-10-08'],
        ['Ratna Sari', 'ratna.karyawan@example.com', '081234567805', 'karyawan', 'marketing', '2026-10-09'],
        ['Hendra Gunawan', 'hendra.karyawan@example.com', '081234567806', 'karyawan', 'operasional', '2026-10-10'],
        ['Maya Putri', 'maya.karyawan@example.com', '081234567807', 'karyawan', 'keuangan', '2026-10-11'],
        ['Eko Nugroho', 'eko.karyawan@example.com', '081234567808', 'karyawan', 'hrd', '2026-10-12'],
        ['Nur Aisyah', 'nur.karyawan@example.com', '081234567809', 'karyawan', 'marketing', '2026-10-13'],
        ['Bayu Pratama', 'bayu.karyawan@example.com', '081234567810', 'karyawan', 'operasional', '2026-10-14'],
        ['Andi Saputra', 'andi.marketing@example.com', '081234568101', 'karyawan', 'marketing', '2026-10-20'],
        ['Bella Kartika', 'bella.marketing@example.com', '081234568102', 'karyawan', 'marketing', '2026-10-21'],
        ['Candra Wijaya', 'candra.marketing@example.com', '081234568103', 'karyawan', 'marketing', '2026-10-22'],
        ['Dinda Maharani', 'dinda.marketing@example.com', '081234568104', 'karyawan', 'marketing', '2026-10-23'],
        ['Fajar Ramadhan', 'fajar.marketing@example.com', '081234568105', 'karyawan', 'marketing', '2026-10-24'],
        ['Gita Puspita', 'gita.marketing@example.com', '081234568106', 'karyawan', 'marketing', '2026-10-25'],
    ];
    $users = [];
    foreach ($userSeeds as [$name, $email, $phone, $role, $division, $createdAt]) {
        [$id, $created] = seed_ensure($db, 'users', ['email' => $email], [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $demoHash,
            'role' => $role,
            'division' => $division,
            'status' => 'aktif',
            'created_at' => $createdAt,
        ]);
        if ($created) {
            seed_bump('users');
        }
        $users[$email] = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'division' => $division,
        ];
    }

    $studentEmails = [
        'budi.student@example.com', 'ani@example.com', 'rina@example.com', 'dimas@example.com',
        'lina@example.com', 'raka@example.com', 'sari@example.com', 'fajar.nugroho@example.com',
        'intan@example.com', 'bayu.wijaya@example.com', 'nabila@example.com', 'yoga@example.com',
    ];
    $instructorEmails = [
        'ahmad@example.com', 'siti@example.com', 'budi@example.com',
        'dian.instruktur@example.com', 'rudi.instruktur@example.com',
    ];
    $employeeEmails = [
        'dewi.karyawan@example.com', 'agus.karyawan@example.com', 'sri.karyawan@example.com',
        'joko.karyawan@example.com', 'ratna.karyawan@example.com', 'hendra.karyawan@example.com',
        'maya.karyawan@example.com', 'eko.karyawan@example.com', 'nur.karyawan@example.com',
        'bayu.karyawan@example.com', 'andi.marketing@example.com', 'bella.marketing@example.com',
        'candra.marketing@example.com', 'dinda.marketing@example.com', 'fajar.marketing@example.com',
        'gita.marketing@example.com',
    ];
    $marketingEmails = [
        'dewi.karyawan@example.com', 'ratna.karyawan@example.com', 'nur.karyawan@example.com',
        'andi.marketing@example.com', 'bella.marketing@example.com', 'candra.marketing@example.com',
        'dinda.marketing@example.com', 'fajar.marketing@example.com', 'gita.marketing@example.com',
    ];
    $adminId = $users['admin@example.com']['id'];
    seed_out('Pengguna siap: ' . count($users) . ' akun demo');

    // ============================================================
    // 4. KURSUS, VERSI KURIKULUM, MODUL, MATERI
    // ============================================================
    $courseSeeds = [
        [
            'title' => 'Pelatihan Manajemen Dasar',
            'category' => 'Pelatihan',
            'categorySlug' => 'pelatihan',
            'instructor' => 'ahmad@example.com',
            'createdAt' => '2026-09-20',
            'description' => 'Pelatihan ini membahas konsep dasar manajemen, perencanaan, organisasi, kepemimpinan, dan pengendalian untuk meningkatkan efektivitas kerja.',
            'location' => 'Gubeng',
            'startDate' => '2027-01-12',
            'meetings' => 8,
            'price' => 500000,
            'discountPrice' => 400000,
            'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=600&h=400&q=80',
            'modules' => [
                'Pengantar Manajemen', 'Perencanaan (Planning)', 'Pengorganisasian (Organizing)',
                'Kepemimpinan (Leading)', 'Pengendalian (Controlling)', 'Manajemen Sumber Daya Manusia',
                'Manajemen Waktu & Produktivitas', 'Evaluasi & Studi Kasus',
            ],
        ],
        [
            'title' => 'Komunikasi Efektif di Tempat Kerja',
            'category' => 'Soft Skill',
            'categorySlug' => 'soft-skill',
            'instructor' => 'siti@example.com',
            'createdAt' => '2026-09-25',
            'description' => 'Meningkatkan kemampuan komunikasi verbal, non-verbal, mendengarkan aktif, dan membangun hubungan yang harmonis di lingkungan kerja.',
            'location' => 'Buduran',
            'startDate' => '2027-01-26',
            'meetings' => 6,
            'price' => 350000,
            'discountPrice' => 297500,
            'image' => 'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=600&h=400&q=80',
            'modules' => [
                'Dasar Komunikasi Efektif', 'Komunikasi Verbal & Non-Verbal', 'Mendengarkan Aktif',
                'Komunikasi dalam Tim', 'Menangani Konflik', 'Presentasi & Negosiasi',
            ],
        ],
        [
            'title' => 'Administrasi Perkantoran Modern',
            'category' => 'Administrasi',
            'categorySlug' => 'administrasi',
            'instructor' => 'budi@example.com',
            'createdAt' => '2026-09-28',
            'description' => 'Pengelolaan arsip, surat menyurat, pengarsipan digital, penggunaan aplikasi perkantoran, dan tata kelola administrasi yang efisien.',
            'location' => 'Candi',
            'startDate' => '2027-02-09',
            'meetings' => 10,
            'price' => 750000,
            'discountPrice' => 525000,
            'image' => 'https://images.unsplash.com/photo-1497215728101-856f4ea42174?auto=format&fit=crop&w=600&h=400&q=80',
            'modules' => [
                'Pengantar Administrasi Perkantoran', 'Pengelolaan Arsip', 'Surat Menyurat',
                'Kearsipan Digital', 'Aplikasi Perkantoran', 'Tata Kelola Administrasi',
                'Evaluasi & Praktik',
            ],
        ],
        [
            'title' => 'Microsoft Office',
            'category' => 'Reguler Class',
            'categorySlug' => 'reguler-class',
            'instructor' => 'ahmad@example.com',
            'createdAt' => '2026-10-02',
            'description' => 'Kelas rombel Microsoft Word, Microsoft Excel, dan Microsoft PowerPoint untuk kebutuhan administrasi perkantoran.',
            'location' => 'Gubeng',
            'startDate' => '2027-02-23',
            'meetings' => 12,
            'price' => 1200000,
            'discountPrice' => 900000,
            'image' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=600&h=400&q=80',
            'modules' => [
                'Pengenalan Microsoft Word', 'Microsoft Excel Dasar', 'Microsoft PowerPoint', 'Praktik Terpadu',
            ],
        ],
        [
            'title' => 'Kursus Server Mode',
            'category' => 'Uji',
            'categorySlug' => 'uji',
            'instructor' => 'ahmad@example.com',
            'createdAt' => '2026-11-12',
            'description' => 'Dari JSDOM',
            'location' => 'Candi',
            'startDate' => '2027-03-09',
            'meetings' => 4,
            'price' => 250000,
            'discountPrice' => 200000,
            'image' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=600&h=400&q=80',
            'modules' => [
                'Pengantar Mode Server', 'Sinkronisasi Data', 'Studi Kasus',
            ],
        ],
        [
            'title' => 'Program Unggulan Teknologi Informasi',
            'category' => 'Teknologi',
            'categorySlug' => 'teknologi',
            'instructor' => 'dian.instruktur@example.com',
            'createdAt' => '2026-11-14',
            'description' => 'Program 12 bulan di bidang Teknologi Informasi: jaringan, pemrograman web, basis data, dan keamanan digital dasar.',
            'location' => 'Gubeng',
            'startDate' => '2027-01-04',
            'meetings' => 48,
            'price' => 1800000,
            'discountPrice' => 1500000,
            'image' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?auto=format&fit=crop&w=600&h=400&q=80',
            'modules' => [
                'Fondasi: Perangkat & Sistem', 'Jaringan Komputer dan Dukungan Teknis',
                'Pemrograman Web Dasar', 'Basis Data dan Pengolahan Data',
                'Keamanan Digital Dasar', 'Proyek Nyata',
                'Portofolio dan Uji Akhir',
            ],
        ],
        [
            'title' => 'Program Unggulan Desain Grafis',
            'category' => 'Desain Grafis',
            'categorySlug' => 'desain-grafis',
            'instructor' => 'rudi.instruktur@example.com',
            'createdAt' => '2026-11-14',
            'description' => 'Program 12 bulan di bidang Desain Grafis: prinsip desain, vektor, branding, dan editing foto/video dasar.',
            'location' => 'Candi',
            'startDate' => '2027-01-04',
            'meetings' => 48,
            'price' => 1800000,
            'discountPrice' => 1500000,
            'image' => 'https://images.unsplash.com/photo-1626785774573-4b799315345d?auto=format&fit=crop&w=600&h=400&q=80',
            'modules' => [
                'Fondasi: Prinsip Desain, Warna, dan Tipografi', 'Ilustrasi dan Desain Vektor',
                'Branding, Logo, dan Konten Media Sosial', 'Editing Foto dan Video Dasar',
                'Proyek Nyata', 'Portofolio dan Uji Akhir',
            ],
        ],
    ];

    $courses = [];
    foreach ($courseSeeds as $seed) {
        $courseTitle = (string) $seed['title'];
        $instructorId = $users[(string) $seed['instructor']]['id'];
        [$courseId, $created] = seed_ensure($db, 'courses', ['title' => $courseTitle], [
            'title' => $courseTitle,
            'category' => (string) $seed['category'],
            'category_id' => $categories[(string) $seed['categorySlug']],
            'description' => (string) $seed['description'],
            'instructor_id' => $instructorId,
            'status' => 'published',
            'location' => (string) $seed['location'],
            'start_date' => (string) $seed['startDate'],
            'meetings' => (int) $seed['meetings'],
            'price' => (float) $seed['price'],
            'discount_price' => (float) $seed['discountPrice'],
            'image' => (string) $seed['image'],
            'created_at' => (string) $seed['createdAt'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $courseDisplay = [
            (string) $seed['category'],
            $categories[(string) $seed['categorySlug']],
            (string) $seed['description'],
            $instructorId,
            (string) $seed['location'],
            (string) $seed['startDate'],
            (int) $seed['meetings'],
            (float) $seed['price'],
            (float) $seed['discountPrice'],
            (string) $seed['image'],
        ];
        if ($created) {
            seed_bump('courses');
        } else {
            $stmt = $db->prepare(
                "UPDATE courses SET category = ?, category_id = ?, description = ?, instructor_id = ?,
                    location = ?, start_date = ?, meetings = ?, price = ?, discount_price = ?, image = ?,
                    status = 'published', updated_at = UTC_TIMESTAMP() WHERE id = ?"
            );
            $stmt->execute(array_merge($courseDisplay, [$courseId]));
        }

        $version = seed_find($db, 'curriculum_versions', ['course_id' => $courseId, 'code' => '1.0']);
        if ($version === null) {
            $versionId = seed_insert($db, 'curriculum_versions', [
                'course_id' => $courseId,
                'code' => '1.0',
                'name' => 'Kurikulum 1.0 — ' . $courseTitle,
                'description' => 'Versi kurikulum demo untuk ' . $courseTitle . '.',
                'status' => 'locked',
                'effective_date' => (string) $seed['createdAt'],
                'notes' => 'Dibuat oleh seed lengkap agar kelas dapat diselenggarakan.',
                'created_by' => $adminId,
                'updated_by' => $adminId,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            seed_bump('curriculum_versions');
        } else {
            $versionId = (int) $version['id'];
            if ($version['status'] !== 'locked') {
                $db->prepare("UPDATE curriculum_versions SET status = 'locked', updated_at = UTC_TIMESTAMP() WHERE id = ?")
                    ->execute([$versionId]);
            }
        }

        $moduleRows = [];
        foreach ($seed['modules'] as $index => $moduleTitle) {
            $fullModuleTitle = 'Modul ' . ($index + 1) . ': ' . $moduleTitle;
            [$moduleId, $moduleCreated] = seed_ensure($db, 'modules', [
                'course_id' => $courseId,
                'curriculum_version_id' => $versionId,
                'title' => $fullModuleTitle,
            ], [
                'course_id' => $courseId,
                'curriculum_version_id' => $versionId,
                'instructor_id' => $instructorId,
                'title' => $fullModuleTitle,
                'description' => 'Materi ' . strtolower((string) $moduleTitle) . ' pada kursus ' . $courseTitle . '.',
                'sort_order' => $index + 1,
                'created_at' => (string) $seed['createdAt'] . ' 00:00:00',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($moduleCreated) {
                seed_bump('modules');
            }

            $lessonIds = [];
            for ($lessonIndex = 1; $lessonIndex <= 3; $lessonIndex++) {
                $suffix = $lessonIndex === 1 ? ' - Konsep Dasar' : ($lessonIndex === 2 ? ' - Penerapan' : ' - Studi Kasus');
                $lessonTitle = ($index + 1) . '.' . $lessonIndex . ' ' . $moduleTitle . $suffix;
                $isVideo = $lessonIndex === 3;
                $content = $isVideo
                    ? '<p>Simulasi video pembelajaran untuk materi <strong>' . $moduleTitle . '</strong>. Pada versi produksi, konten video dapat di-embed dari YouTube atau Vimeo.</p>'
                    : '<p>Materi ini membahas <strong>' . $moduleTitle . '</strong> secara mendalam, mulai dari konsep dasar, penerapan di tempat kerja, hingga studi kasus sederhana.</p><ul><li>Pahami konsep utama materi.</li><li>Perhatikan contoh penerapan.</li><li>Kerjakan latihan mandiri.</li></ul>';
                [$lessonId, $lessonCreated] = seed_ensure($db, 'lessons', [
                    'module_id' => $moduleId,
                    'title' => $lessonTitle,
                ], [
                    'module_id' => $moduleId,
                    'course_id' => $courseId,
                    'curriculum_version_id' => $versionId,
                    'instructor_id' => $instructorId,
                    'title' => $lessonTitle,
                    'type' => $isVideo ? 'video' : 'text',
                    'content' => $content,
                    'sort_order' => $lessonIndex,
                    'created_at' => (string) $seed['createdAt'] . ' 00:00:00',
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($lessonCreated) {
                    seed_bump('lessons');
                }
                $lessonIds[] = $lessonId;
            }

            $moduleRows[] = [
                'id' => $moduleId,
                'title' => (string) $moduleTitle,
                'lessons' => $lessonIds,
            ];
        }

        $courses[$courseTitle] = [
            'id' => $courseId,
            'versionId' => $versionId,
            'instructorId' => $instructorId,
            'modules' => $moduleRows,
            'description' => (string) $seed['description'],
        ];
    }
    seed_out('Kursus siap: ' . count($courses) . ' kursus dengan versi, modul, dan materi');

    // ============================================================
    // 5. KELAS / ANGKATAN & HISTORI TENTOR
    // ============================================================
    $classSeeds = [
        [
            'code' => 'ANG-2026-MNJ-01', 'name' => 'Angkatan 2026 — Pelatihan Manajemen Dasar',
            'course' => 'Pelatihan Manajemen Dasar', 'branch' => 'CBG-JKT', 'instructor' => 'ahmad@example.com',
            'start' => '2026-10-01', 'end' => '2026-12-31', 'quota' => 30,
            'location' => 'Kampus Pusat', 'room' => 'R-101', 'schedule' => 'Senin & Rabu, 09.00-11.00',
            'status' => 'ongoing', 'notes' => 'Kelas unggulan dengan peserta dari berbagai instansi.',
        ],
        [
            'code' => 'ANG-2026-MNJ-02', 'name' => 'Angkatan 2026 — Manajemen Dasar Kelas Malam',
            'course' => 'Pelatihan Manajemen Dasar', 'branch' => 'CBG-BDG', 'instructor' => 'ahmad@example.com',
            'start' => '2026-11-02', 'end' => '2027-01-30', 'quota' => 24,
            'location' => 'Kampus Bandung', 'room' => 'R-203', 'schedule' => 'Selasa & Kamis, 18.30-20.30',
            'status' => 'open', 'notes' => 'Kelas malam untuk pekerja yang ingin meningkatkan kemampuan manajerial.',
        ],
        [
            'code' => 'ANG-2026-KOM-01', 'name' => 'Angkatan 2026 — Komunikasi Efektif',
            'course' => 'Komunikasi Efektif di Tempat Kerja', 'branch' => 'CBG-JKT', 'instructor' => 'siti@example.com',
            'start' => '2026-10-15', 'end' => '2026-12-18', 'quota' => 20,
            'location' => 'Kampus Pusat', 'room' => 'R-102', 'schedule' => 'Jumat, 13.00-16.00',
            'status' => 'ongoing', 'notes' => 'Fokus pada praktik komunikasi dan simulasi presentasi.',
        ],
        [
            'code' => 'ANG-2026-ADM-01', 'name' => 'Angkatan 2026 — Administrasi Perkantoran',
            'course' => 'Administrasi Perkantoran Modern', 'branch' => 'CBG-SBY', 'instructor' => 'budi@example.com',
            'start' => '2026-10-12', 'end' => '2026-12-22', 'quota' => 22,
            'location' => 'Kampus Surabaya', 'room' => 'R-301', 'schedule' => 'Senin & Kamis, 08.00-11.00',
            'status' => 'ongoing', 'notes' => 'Menggunakan studi kasus kearsipan digital dan surat menyurat.',
        ],
        [
            'code' => 'ANG-2026-MSO-01', 'name' => 'Angkatan 2026 — Microsoft Office (Selesai)',
            'course' => 'Microsoft Office', 'branch' => 'CBG-BDG', 'instructor' => 'ahmad@example.com',
            'start' => '2026-08-03', 'end' => '2026-10-30', 'quota' => 18,
            'location' => 'Kampus Bandung', 'room' => 'Lab-1', 'schedule' => 'Senin & Rabu, 09.00-12.00',
            'status' => 'completed', 'notes' => 'Kelas yang telah selesai dan menghasilkan sertifikat.',
        ],
        [
            'code' => 'ANG-2026-MSO-02', 'name' => 'Angkatan 2026 — Microsoft Office Akhir Tahun',
            'course' => 'Microsoft Office', 'branch' => 'CBG-YOG', 'instructor' => 'dian.instruktur@example.com',
            'start' => '2026-11-20', 'end' => '2027-01-22', 'quota' => 20,
            'location' => 'Kampus Yogyakarta', 'room' => 'Lab-2', 'schedule' => 'Selasa & Jumat, 09.00-12.00',
            'status' => 'open', 'notes' => 'Kelas baru dengan kuota terbatas untuk umum.',
        ],
    ];

    $classes = [];
    foreach ($classSeeds as $seed) {
        $course = $courses[(string) $seed['course']];
        $instructorId = $users[(string) $seed['instructor']]['id'];
        [$classId, $created] = seed_ensure($db, 'classes', ['code' => (string) $seed['code']], [
            'code' => (string) $seed['code'],
            'name' => (string) $seed['name'],
            'course_id' => $course['id'],
            'curriculum_version_id' => $course['versionId'],
            'branch_id' => $branches[(string) $seed['branch']],
            'instructor_id' => $instructorId,
            'period_start' => (string) $seed['start'],
            'period_end' => (string) $seed['end'],
            'quota' => (int) $seed['quota'],
            'location' => (string) $seed['location'],
            'room' => (string) $seed['room'],
            'schedule_note' => (string) $seed['schedule'],
            'planned_jp' => 32,
            'status' => (string) $seed['status'],
            'legacy_import' => 0,
            'notes' => (string) $seed['notes'],
            'created_by' => $adminId,
            'updated_by' => $adminId,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($created) {
            seed_bump('classes');
            seed_audit(
                $db,
                'create',
                'class',
                $classId,
                (string) $seed['name'],
                'Membuat kelas ' . $seed['code'] . ' untuk kursus "' . $seed['course'] . '".',
                $adminId
            );
        }

        $classRow = seed_find($db, 'classes', ['id' => $classId]);
        $history = seed_find($db, 'class_instructor_histories', [
            'class_id' => $classId,
            'instructor_id' => $instructorId,
            'ended_at' => null,
        ]);
        if ($history === null) {
            seed_insert($db, 'class_instructor_histories', [
                'class_id' => $classId,
                'instructor_id' => $instructorId,
                'started_at' => (string) $seed['start'],
                'ended_at' => null,
                'reason' => 'Penetapan tentor awal dari seed lengkap.',
                'actor_id' => $adminId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            seed_bump('class_instructor_histories');
        }

        $classes[(string) $seed['code']] = [
            'id' => $classId,
            'name' => $classRow ? (string) $classRow['name'] : (string) $seed['name'],
            'courseId' => $course['id'],
            'versionId' => $course['versionId'],
            'branchId' => $branches[(string) $seed['branch']],
            'instructorId' => $instructorId,
            'periodStart' => $classRow ? (string) $classRow['period_start'] : (string) $seed['start'],
            'periodEnd' => $classRow ? (string) $classRow['period_end'] : (string) $seed['end'],
            'status' => $classRow ? (string) $classRow['status'] : (string) $seed['status'],
            'modules' => $course['modules'],
            'location' => (string) $seed['location'],
            'room' => (string) $seed['room'],
            'courseTitle' => (string) $seed['course'],
        ];
    }
    seed_out('Kelas siap: ' . count($classes));

    // ============================================================
    // 6. ENROLLMENT PESERTA KE KELAS
    // ============================================================
    $enrollmentSeeds = [
        'ANG-2026-MNJ-01' => [
            ['budi.student@example.com', 'active', '2026-10-01'],
            ['ani@example.com', 'active', '2026-10-05'],
            ['rina@example.com', 'active', '2026-10-10'],
            ['dimas@example.com', 'active', '2026-10-12'],
            ['lina@example.com', 'active', '2026-10-13'],
            ['raka@example.com', 'active', '2026-10-14'],
            ['sari@example.com', 'active', '2026-10-15'],
            ['fajar.nugroho@example.com', 'active', '2026-10-16'],
        ],
        'ANG-2026-MNJ-02' => [
            ['budi.student@example.com', 'active', '2026-10-20'],
            ['ani@example.com', 'active', '2026-10-21'],
            ['dimas@example.com', 'pending', '2026-10-22'],
            ['intan@example.com', 'active', '2026-10-23'],
            ['nabila@example.com', 'pending', '2026-10-24'],
        ],
        'ANG-2026-KOM-01' => [
            ['rina@example.com', 'active', '2026-10-12'],
            ['lina@example.com', 'active', '2026-10-13'],
            ['raka@example.com', 'active', '2026-10-14'],
            ['bayu.wijaya@example.com', 'active', '2026-10-15'],
            ['yoga@example.com', 'active', '2026-10-16'],
        ],
        'ANG-2026-ADM-01' => [
            ['budi.student@example.com', 'active', '2026-10-08'],
            ['dimas@example.com', 'active', '2026-10-09'],
            ['sari@example.com', 'active', '2026-10-10'],
            ['intan@example.com', 'active', '2026-10-11'],
            ['nabila@example.com', 'active', '2026-10-12'],
            ['yoga@example.com', 'withdrawn', '2026-10-13'],
        ],
        'ANG-2026-MSO-01' => [
            ['ani@example.com', 'completed', '2026-08-01'],
            ['lina@example.com', 'completed', '2026-08-02'],
            ['sari@example.com', 'completed', '2026-08-03'],
            ['fajar.nugroho@example.com', 'completed', '2026-08-04'],
            ['raka@example.com', 'completed', '2026-08-05'],
        ],
        'ANG-2026-MSO-02' => [
            ['budi.student@example.com', 'pending', '2026-11-10'],
            ['ani@example.com', 'pending', '2026-11-11'],
            ['intan@example.com', 'pending', '2026-11-12'],
        ],
    ];

    $enrollments = [];
    foreach ($enrollmentSeeds as $classCode => $rows) {
        $class = $classes[$classCode];
        $enrollments[$classCode] = [];
        foreach ($rows as [$studentEmail, $status, $enrolledAt]) {
            $student = $users[$studentEmail];
            [$enrollmentId, $created] = seed_ensure($db, 'enrollments', [
                'student_id' => $student['id'],
                'class_id' => $class['id'],
            ], [
                'student_id' => $student['id'],
                'course_id' => $class['courseId'],
                'class_id' => $class['id'],
                'enrolled_by' => 'admin',
                'enrolled_at' => $enrolledAt,
                'status' => $status,
                'created_at' => $enrolledAt . ' 08:00:00',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($created) {
                seed_bump('enrollments');
            }
            $row = seed_find($db, 'enrollments', ['id' => $enrollmentId]);
            $enrollments[$classCode][] = [
                'id' => $enrollmentId,
                'studentId' => (int) $student['id'],
                'studentEmail' => $studentEmail,
                'studentName' => (string) $student['name'],
                'status' => $row ? (string) $row['status'] : $status,
                'enrolledAt' => $row ? (string) $row['enrolled_at'] : $enrolledAt,
            ];
        }
    }
    seed_out('Enrollment siap: ' . array_sum(array_map('count', $enrollments)) . ' penugasan peserta');

    // ============================================================
    // 7. SESI PERTEMUAN
    // ============================================================
    $sessionPlans = [
        'ANG-2026-MNJ-01' => ['start' => 5, 'total' => 9, 'completedThrough' => 8, 'rescheduled' => null, 'startTime' => '09:00:00', 'endTime' => '11:00:00'],
        'ANG-2026-MNJ-02' => ['start' => 1, 'total' => 4, 'completedThrough' => 0, 'rescheduled' => null, 'startTime' => '18:30:00', 'endTime' => '20:30:00'],
        'ANG-2026-KOM-01' => ['start' => 1, 'total' => 7, 'completedThrough' => 5, 'rescheduled' => 6, 'startTime' => '13:00:00', 'endTime' => '16:00:00'],
        'ANG-2026-ADM-01' => ['start' => 1, 'total' => 6, 'completedThrough' => 5, 'rescheduled' => null, 'startTime' => '08:00:00', 'endTime' => '11:00:00'],
        'ANG-2026-MSO-01' => ['start' => 1, 'total' => 4, 'completedThrough' => 4, 'rescheduled' => null, 'startTime' => '09:00:00', 'endTime' => '12:00:00'],
        'ANG-2026-MSO-02' => ['start' => 1, 'total' => 3, 'completedThrough' => 0, 'rescheduled' => null, 'startTime' => '09:00:00', 'endTime' => '12:00:00'],
    ];
    $sessionIds = [];
    foreach ($sessionPlans as $classCode => $plan) {
        $class = $classes[$classCode];
        $moduleCount = max(1, count($class['modules']));
        $sessionIds[$classCode] = [];
        for ($number = $plan['start']; $number < $plan['start'] + $plan['total']; $number++) {
            $moduleIndex = min($number - 1, $moduleCount - 1);
            $module = $class['modules'][$moduleIndex];
            $sessionDate = seed_add_days($class['periodStart'], ($number - 1) * 7);
            if ($plan['rescheduled'] !== null && $number === $plan['rescheduled']) {
                $status = 'rescheduled';
            } elseif ($number <= $plan['completedThrough']) {
                $status = 'completed';
            } elseif ($class['status'] === 'ongoing' && $number === $plan['completedThrough'] + 1) {
                $status = 'confirmed';
            } else {
                $status = 'scheduled';
            }
            $title = 'Pertemuan ' . $number . ': ' . $module['title'];
            if ($plan['rescheduled'] !== null && $number === $plan['rescheduled'] + 1) {
                $title = 'Pertemuan ' . ($number - 1) . ' (Pengganti): ' . $module['title'];
            }
            [$sessionId, $created] = seed_ensure($db, 'class_sessions', [
                'class_id' => $class['id'],
                'session_number' => $number,
            ], [
                'class_id' => $class['id'],
                'session_number' => $number,
                'title' => $title,
                'module_id' => $module['id'],
                'session_date' => $sessionDate,
                'start_time' => $plan['startTime'],
                'end_time' => $plan['endTime'],
                'location' => $class['location'],
                'room' => $class['room'],
                'instructor_id' => $class['instructorId'],
                'planned_jp' => 4,
                'actual_jp' => $status === 'completed' ? 4 : 0,
                'status' => $status,
                'rescheduled_from_id' => null,
                'notes' => $status === 'completed'
                    ? 'Sesi berjalan lancar dan presensi telah dicatat.'
                    : 'Sesi terjadwal pada kalender akademik.',
                'created_by' => $class['instructorId'],
                'updated_by' => $class['instructorId'],
                'created_at' => $sessionDate . ' 07:00:00',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($created) {
                seed_bump('class_sessions');
            }
            $sessionIds[$classCode][$number] = $sessionId;
        }

        if ($plan['rescheduled'] !== null) {
            $originId = $sessionIds[$classCode][$plan['rescheduled']];
            $replacementId = $sessionIds[$classCode][$plan['rescheduled'] + 1] ?? null;
            $origin = seed_find($db, 'class_sessions', ['id' => $originId]);
            if ($origin !== null && $origin['status'] !== 'rescheduled') {
                $db->prepare("UPDATE class_sessions SET status = 'rescheduled', actual_jp = 0, updated_at = UTC_TIMESTAMP() WHERE id = ?")
                    ->execute([$originId]);
            }
            if ($replacementId !== null) {
                $replacement = seed_find($db, 'class_sessions', ['id' => $replacementId]);
                if ($replacement !== null && (int) $replacement['rescheduled_from_id'] !== $originId) {
                    $db->prepare('UPDATE class_sessions SET rescheduled_from_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                        ->execute([$originId, $replacementId]);
                }
            }
        }
    }
    seed_out('Sesi pertemuan siap: ' . array_sum(array_map('count', $sessionIds)) . ' sesi');

    // ============================================================
    // 8. PRESENSI PESERTA
    // ============================================================
    $attendancePattern = ['present', 'present', 'late', 'present', 'permission', 'present', 'sick', 'absent', 'excused'];
    foreach ($classes as $classCode => $class) {
        $stmt = $db->prepare(
            "SELECT * FROM class_sessions WHERE class_id = ? AND status = 'completed' ORDER BY session_number ASC"
        );
        $stmt->execute([$class['id']]);
        $completedSessions = $stmt->fetchAll();
        foreach ($completedSessions as $session) {
            foreach ($enrollments[$classCode] as $enrollIndex => $enrollment) {
                if (in_array($enrollment['status'], ['withdrawn', 'cancelled'], true)) {
                    continue;
                }
                $status = $attendancePattern[((int) $session['session_number'] + $enrollIndex) % count($attendancePattern)];
                $note = '';
                if ($status === 'late') {
                    $note = 'Datang terlambat 10-15 menit dari jadwal.';
                } elseif ($status === 'permission') {
                    $note = 'Izin keperluan keluarga yang telah disetujui instruktur.';
                } elseif ($status === 'sick') {
                    $note = 'Sakit dengan surat keterangan dokter.';
                } elseif ($status === 'absent') {
                    $note = 'Tidak hadir tanpa keterangan.';
                } elseif ($status === 'excused') {
                    $note = 'Dikecualikan karena tugas dinas luar.';
                }
                [, $created] = seed_ensure($db, 'attendances', [
                    'session_id' => (int) $session['id'],
                    'enrollment_id' => $enrollment['id'],
                ], [
                    'session_id' => (int) $session['id'],
                    'enrollment_id' => $enrollment['id'],
                    'student_id' => $enrollment['studentId'],
                    'status' => $status,
                    'recorded_by' => $class['instructorId'],
                    'recorded_at' => $session['session_date'] . ' 11:05:00',
                    'note' => $note,
                    'correction_reason' => '',
                    'created_at' => $session['session_date'] . ' 11:05:00',
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($created) {
                    seed_bump('attendances');
                }
            }
        }
    }
    seed_out('Presensi siap: ' . (int) $db->query('SELECT COUNT(*) FROM attendances')->fetchColumn() . ' baris');

    // ============================================================
    // 9. KOREKSI PRESENSI
    // ============================================================
    $correctionSpecs = [
        ['class' => 'ANG-2026-MNJ-01', 'session' => 5, 'enrollIndex' => 2, 'reason' => 'Koreksi demo: peserta menitipkan surat izin susulan.'],
        ['class' => 'ANG-2026-KOM-01', 'session' => 1, 'enrollIndex' => 1, 'reason' => 'Koreksi demo: keterlambatan dicatat sebagai hadir setelah verifikasi.'],
        ['class' => 'ANG-2026-ADM-01', 'session' => 2, 'enrollIndex' => 5, 'reason' => 'Koreksi demo: peserta hadir melalui kelas daring pengganti.'],
    ];
    foreach ($correctionSpecs as $spec) {
        $classCode = $spec['class'];
        $sessionId = $sessionIds[$classCode][$spec['session']] ?? null;
        $enrollment = $enrollments[$classCode][$spec['enrollIndex']] ?? null;
        if ($sessionId === null || $enrollment === null) {
            continue;
        }
        $attendance = seed_find($db, 'attendances', [
            'session_id' => $sessionId,
            'enrollment_id' => $enrollment['id'],
        ]);
        if ($attendance === null) {
            continue;
        }
        $existingCorrection = seed_find($db, 'attendance_corrections', [
            'attendance_id' => (int) $attendance['id'],
            'reason' => $spec['reason'],
        ]);
        if ($existingCorrection !== null) {
            continue;
        }
        $oldStatus = (string) $attendance['status'];
        seed_insert($db, 'attendance_corrections', [
            'attendance_id' => (int) $attendance['id'],
            'session_id' => $sessionId,
            'enrollment_id' => $enrollment['id'],
            'old_status' => $oldStatus,
            'new_status' => 'present',
            'actor_id' => $adminId,
            'actor_name' => 'Administrator',
            'actor_role' => 'admin',
            'reason' => $spec['reason'],
            'corrected_at' => gmdate('Y-m-d H:i:s'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        seed_bump('attendance_corrections');
        $db->prepare(
            "UPDATE attendances SET status = 'present', corrected_at = UTC_TIMESTAMP(),
                correction_reason = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?"
        )->execute([$spec['reason'], (int) $attendance['id']]);
    }
    seed_out('Koreksi presensi siap');

    // ============================================================
    // 10. PENGATURAN AKSES & MATERI TAMBAHAN KELAS
    // ============================================================
    $accessModes = ['open', 'sequential', 'scheduled', 'conditional'];
    $materials = [];
    foreach ($classes as $classCode => $class) {
        $mode = $accessModes[($class['id'] - 1) % count($accessModes)];
        [, $settingsCreated] = seed_ensure($db, 'class_material_settings', ['class_id' => $class['id']], [
            'class_id' => $class['id'],
            'access_mode' => $mode,
            'require_instructor_approval' => $mode === 'conditional' ? 1 : 0,
            'notes' => 'Pengaturan akses materi demo untuk kelas ' . $classCode . '.',
            'updated_by' => $class['instructorId'],
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($settingsCreated) {
            seed_bump('class_material_settings');
        }

        $courseLessons = [];
        foreach ($class['modules'] as $module) {
            foreach ($module['lessons'] as $lessonId) {
                $courseLessons[] = $lessonId;
            }
        }
        $sessionStmt = $db->prepare('SELECT * FROM class_sessions WHERE class_id = ? ORDER BY session_number ASC LIMIT 1');
        $sessionStmt->execute([$class['id']]);
        $firstSession = $sessionStmt->fetch() ?: null;
        $firstModule = $class['modules'][0];

        $materialSeeds = [
            [
                'title' => 'Panduan Belajar — ' . $class['name'],
                'type' => 'text',
                'description' => 'Panduan umum mengikuti kelas, etika pembelajaran, dan penilaian.',
                'content' => '<p>Selamat mengikuti kelas <strong>' . $class['name'] . '</strong>. Bacalah panduan ini sebelum pertemuan pertama.</p><ol><li>Hadir tepat waktu sesuai jadwal.</li><li>Aktif dalam diskusi dan praktik.</li><li>Kerjakan seluruh tugas sebelum tenggat.</li></ol>',
                'lesson_id' => null,
                'session_id' => null,
                'access_level' => 'required',
                'file_name' => '',
                'file_path' => '',
                'file_size' => null,
                'mime_type' => '',
            ],
            [
                'title' => 'Rekaman Pertemuan — ' . ($firstSession ? $firstSession['title'] : $firstModule['title']),
                'type' => 'video',
                'description' => 'Rekaman sesi untuk dipelajari ulang sebelum pertemuan berikutnya.',
                'content' => '<iframe src="https://www.youtube.com/embed/demo-elips" title="Rekaman kelas" allowfullscreen></iframe>',
                'lesson_id' => $courseLessons[0] ?? null,
                'session_id' => $firstSession ? (int) $firstSession['id'] : null,
                'access_level' => 'optional',
                'file_name' => '',
                'file_path' => '',
                'file_size' => null,
                'mime_type' => '',
            ],
            [
                'title' => 'Referensi Tambahan — ' . $class['courseTitle'],
                'type' => 'link',
                'description' => 'Bahan bacaan tambahan untuk memperdalam materi kelas.',
                'content' => 'https://elips.example.com/referensi/' . strtolower(str_replace(' ', '-', $class['courseTitle'])),
                'lesson_id' => $courseLessons[1] ?? ($courseLessons[0] ?? null),
                'session_id' => null,
                'access_level' => 'reference',
                'file_name' => '',
                'file_path' => '',
                'file_size' => null,
                'mime_type' => '',
            ],
            [
                'title' => 'Lembar Kerja Praktik — ' . $classCode,
                'type' => 'document',
                'description' => 'Lembar kerja yang diisi peserta selama praktik di kelas.',
                'content' => '<p>Unduh lembar kerja, isi sesuai instruksi, lalu unggah kembali melalui tugas kelas.</p>',
                'lesson_id' => $courseLessons[2] ?? ($courseLessons[0] ?? null),
                'session_id' => null,
                'access_level' => 'optional',
                'file_name' => 'lembar-kerja-' . strtolower($classCode) . '.pdf',
                'file_path' => '/uploads/kelas/' . strtolower($classCode) . '/lembar-kerja.pdf',
                'file_size' => 245760,
                'mime_type' => 'application/pdf',
            ],
        ];

        $materials[$classCode] = [];
        foreach ($materialSeeds as $index => $material) {
            $isHidden = $classCode === 'ANG-2026-MNJ-02' && $index === 3;
            [$materialId, $created] = seed_ensure($db, 'class_materials', [
                'class_id' => $class['id'],
                'title' => $material['title'],
            ], [
                'class_id' => $class['id'],
                'lesson_id' => $material['lesson_id'],
                'session_id' => $material['session_id'],
                'title' => $material['title'],
                'description' => $material['description'],
                'type' => $material['type'],
                'content' => $material['content'],
                'file_name' => $material['file_name'],
                'file_path' => $material['file_path'],
                'file_size' => $material['file_size'],
                'mime_type' => $material['mime_type'],
                'access_level' => $material['access_level'],
                'available_from' => $class['periodStart'],
                'status' => $isHidden ? 'hidden' : 'active',
                'reason' => $isHidden ? 'Materi disembunyikan sampai instruktur membuka akses.' : '',
                'sort_order' => $index + 1,
                'created_by' => $class['instructorId'],
                'updated_by' => $class['instructorId'],
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($created) {
                seed_bump('class_materials');
            }
            $materials[$classCode][] = [
                'id' => $materialId,
                'status' => $isHidden ? 'hidden' : 'active',
                'index' => $index,
            ];
        }
    }
    seed_out('Materi tambahan siap: ' . array_sum(array_map('count', $materials)) . ' materi');

    // ============================================================
    // 11. PROGRES MATERI & PROGRES PELAJARAN PESERTA
    // ============================================================
    foreach ($classes as $classCode => $class) {
        $courseLessons = [];
        foreach ($class['modules'] as $module) {
            foreach ($module['lessons'] as $lessonId) {
                $courseLessons[] = $lessonId;
            }
        }
        $totalLessons = count($courseLessons);
        foreach ($enrollments[$classCode] as $enrollIndex => $enrollment) {
            if ($class['status'] === 'completed') {
                $lessonCount = $totalLessons;
            } elseif ($class['status'] === 'ongoing') {
                $lessonCount = min($totalLessons, 4 + ($enrollIndex % 5));
            } else {
                $lessonCount = min($totalLessons, 2 + ($enrollIndex % 3));
            }
            for ($lessonIndex = 0; $lessonIndex < $lessonCount; $lessonIndex++) {
                $lessonId = $courseLessons[$lessonIndex];
                [, $created] = seed_ensure($db, 'class_lesson_progress', [
                    'enrollment_id' => $enrollment['id'],
                    'lesson_id' => $lessonId,
                ], [
                    'class_id' => $class['id'],
                    'enrollment_id' => $enrollment['id'],
                    'student_id' => $enrollment['studentId'],
                    'lesson_id' => $lessonId,
                    'completed_by' => $class['instructorId'],
                    'completed_at' => seed_add_days($class['periodStart'], ($lessonIndex + 1) * 2) . ' 15:00:00',
                    'created_at' => seed_add_days($class['periodStart'], ($lessonIndex + 1) * 2) . ' 15:00:00',
                ]);
                if ($created) {
                    seed_bump('class_lesson_progress');
                }
            }

            foreach ($materials[$classCode] as $material) {
                if ($material['status'] !== 'active') {
                    continue;
                }
                $shouldComplete = $class['status'] === 'completed'
                    || (($enrollIndex + $material['index']) % 3 !== 0);
                if (!$shouldComplete) {
                    continue;
                }
                [, $created] = seed_ensure($db, 'class_material_progress', [
                    'enrollment_id' => $enrollment['id'],
                    'material_id' => $material['id'],
                ], [
                    'class_id' => $class['id'],
                    'enrollment_id' => $enrollment['id'],
                    'student_id' => $enrollment['studentId'],
                    'material_id' => $material['id'],
                    'completed_by' => $class['instructorId'],
                    'completed_at' => seed_add_days($class['periodStart'], ($material['index'] + 1) * 2) . ' 16:00:00',
                    'created_at' => seed_add_days($class['periodStart'], ($material['index'] + 1) * 2) . ' 16:00:00',
                ]);
                if ($created) {
                    seed_bump('class_material_progress');
                }
            }
        }
    }
    seed_out('Progres pelajaran dan materi siap');

    // ============================================================
    // 12. COMPLETED LESSONS (LEGACY)
    // ============================================================
    $legacyPlans = [
        'ANG-2026-MSO-01' => 99,
        'ANG-2026-MNJ-01' => 6,
        'ANG-2026-KOM-01' => 4,
    ];
    foreach ($legacyPlans as $classCode => $maxLessons) {
        $class = $classes[$classCode];
        $courseLessons = [];
        foreach ($class['modules'] as $module) {
            foreach ($module['lessons'] as $lessonId) {
                $courseLessons[] = $lessonId;
            }
        }
        foreach ($enrollments[$classCode] as $enrollIndex => $enrollment) {
            $limit = min(count($courseLessons), $maxLessons);
            for ($lessonIndex = 0; $lessonIndex < $limit; $lessonIndex++) {
                $lessonId = $courseLessons[$lessonIndex];
                [, $created] = seed_ensure($db, 'completed_lessons', [
                    'student_id' => $enrollment['studentId'],
                    'lesson_id' => $lessonId,
                ], [
                    'student_id' => $enrollment['studentId'],
                    'course_id' => $class['courseId'],
                    'lesson_id' => $lessonId,
                    'completed_at' => seed_add_days($class['periodStart'], ($lessonIndex + 1) * 2) . ' 14:00:00',
                ]);
                if ($created) {
                    seed_bump('completed_lessons');
                }
            }
        }
    }
    seed_out('Completed lessons legacy siap');

    // ============================================================
    // 13. TUGAS, LAMPIRAN, RUBRIK, SUBMISSION, DAN REVIEW
    // ============================================================
    $assignmentSeeds = [
        [
            'class' => 'ANG-2026-MNJ-01', 'title' => 'Tugas 3: Studi Kasus Manajemen',
            'moduleIndex' => 6, 'lessonIndex' => 0, 'type' => 'text_submission', 'status' => 'published',
            'requireFile' => false, 'allowedTypes' => '', 'maxFileSize' => null,
            'openOffset' => 14, 'dueOffset' => 28,
            'instructions' => 'Pilih satu studi kasus organisasi, lalu susun analisis manajemen dalam 500-700 kata. Gunakan referensi dari modul 1 sampai 7.',
        ],
        [
            'class' => 'ANG-2026-MNJ-02', 'title' => 'Tugas 1: Refleksi Gaya Kepemimpinan',
            'moduleIndex' => 0, 'lessonIndex' => 0, 'type' => 'text_submission', 'status' => 'published',
            'requireFile' => false, 'allowedTypes' => '', 'maxFileSize' => null,
            'openOffset' => 3, 'dueOffset' => 17,
            'instructions' => 'Tuliskan refleksi singkat mengenai gaya kepemimpinan yang Anda terapkan atau amati di tempat kerja.',
        ],
        [
            'class' => 'ANG-2026-KOM-01', 'title' => 'Tugas 1: Analisis Komunikasi Tim',
            'moduleIndex' => 3, 'lessonIndex' => 1, 'type' => 'file_upload', 'status' => 'published',
            'requireFile' => true, 'allowedTypes' => 'pdf,doc,docx', 'maxFileSize' => 5242880,
            'openOffset' => 7, 'dueOffset' => 21,
            'instructions' => 'Analisis pola komunikasi dalam satu tim kerja, identifikasi hambatan, dan usulkan perbaikan. Unggah dokumen PDF/DOCX.',
        ],
        [
            'class' => 'ANG-2026-ADM-01', 'title' => 'Tugas 1: Rekap Arsip Digital',
            'moduleIndex' => 3, 'lessonIndex' => 0, 'type' => 'file_upload', 'status' => 'published',
            'requireFile' => true, 'allowedTypes' => 'pdf,xlsx', 'maxFileSize' => 5242880,
            'openOffset' => 5, 'dueOffset' => 19,
            'instructions' => 'Buat rekap arsip digital sederhana lengkap dengan metadata dan kode klasifikasi. Unggah dokumen PDF atau XLSX.',
        ],
        [
            'class' => 'ANG-2026-MSO-01', 'title' => 'Tugas Akhir: Presentasi Microsoft Office',
            'moduleIndex' => 3, 'lessonIndex' => 2, 'type' => 'file_upload', 'status' => 'closed',
            'requireFile' => true, 'allowedTypes' => 'pdf,ppt,pptx', 'maxFileSize' => 10485760,
            'openOffset' => 30, 'dueOffset' => 55,
            'instructions' => 'Susun presentasi yang menggabungkan Word, Excel, dan PowerPoint. Unggah berkas presentasi final.',
        ],
        [
            'class' => 'ANG-2026-MSO-02', 'title' => 'Tugas 1: Latihan Format Dokumen',
            'moduleIndex' => 0, 'lessonIndex' => 0, 'type' => 'text_submission', 'status' => 'published',
            'requireFile' => false, 'allowedTypes' => '', 'maxFileSize' => null,
            'openOffset' => 2, 'dueOffset' => 16,
            'instructions' => 'Jelaskan langkah-langkah memformat dokumen Word yang rapi dan profesional.',
        ],
    ];

    foreach ($assignmentSeeds as $seed) {
        $classCode = (string) $seed['class'];
        $class = $classes[$classCode];
        $module = $class['modules'][(int) $seed['moduleIndex']] ?? $class['modules'][0];
        $lessonId = $module['lessons'][(int) $seed['lessonIndex']] ?? $module['lessons'][0];
        [$assignmentId, $created] = seed_ensure($db, 'assignments', [
            'class_id' => $class['id'],
            'title' => (string) $seed['title'],
        ], [
            'class_id' => $class['id'],
            'curriculum_version_id' => $class['versionId'],
            'module_id' => $module['id'],
            'lesson_id' => $lessonId,
            'title' => (string) $seed['title'],
            'instructions' => (string) $seed['instructions'],
            'type' => (string) $seed['type'],
            'open_at' => seed_add_days($class['periodStart'], (int) $seed['openOffset']) . ' 08:00:00',
            'due_at' => seed_add_days($class['periodStart'], (int) $seed['dueOffset']) . ' 23:59:59',
            'max_score' => 100,
            'weight' => 20,
            'require_file' => $seed['requireFile'] ? 1 : 0,
            'allowed_types' => (string) $seed['allowedTypes'],
            'max_file_size' => $seed['maxFileSize'],
            'status' => (string) $seed['status'],
            'created_by' => $class['instructorId'],
            'updated_by' => $class['instructorId'],
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($created) {
            seed_bump('assignments');
        }

        // Lampiran tugas untuk tugas yang mewajibkan berkas.
        if ($seed['requireFile']) {
            [, $fileCreated] = seed_ensure($db, 'assignment_files', [
                'assignment_id' => $assignmentId,
                'file_name' => 'panduan-tugas.pdf',
            ], [
                'assignment_id' => $assignmentId,
                'file_name' => 'panduan-tugas.pdf',
                'file_path' => '/uploads/tugas/' . $assignmentId . '/panduan-tugas.pdf',
                'file_size' => 184320,
                'mime_type' => 'application/pdf',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($fileCreated) {
                seed_bump('assignment_files');
            }
        }
    }

    // Rubrik untuk seluruh tugas yang belum memiliki rubrik.
    $assignmentStmt = $db->query('SELECT * FROM assignments ORDER BY id ASC');
    $allAssignments = $assignmentStmt->fetchAll();
    foreach ($allAssignments as $assignment) {
        $rubricCountStmt = $db->prepare('SELECT COUNT(*) FROM assignment_rubrics WHERE assignment_id = ?');
        $rubricCountStmt->execute([(int) $assignment['id']]);
        if ((int) $rubricCountStmt->fetchColumn() > 0) {
            continue;
        }
        $rubrics = [
            ['Ketepatan isi', 'Isi jawaban sesuai instruksi tugas dan konteks materi.', 40],
            ['Kedalaman analisis', 'Analisis didukung contoh, data, dan referensi yang relevan.', 35],
            ['Kerapian penyajian', 'Struktur, ejaan, dan format berkas rapi serta mudah dibaca.', 25],
        ];
        foreach ($rubrics as $index => [$criterion, $description, $maxScore]) {
            seed_insert($db, 'assignment_rubrics', [
                'assignment_id' => (int) $assignment['id'],
                'criterion' => $criterion,
                'description' => $description,
                'max_score' => $maxScore,
                'sort_order' => $index + 1,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            seed_bump('assignment_rubrics');
        }
    }

    // Submission peserta untuk setiap tugas.
    $submissionStatuses = ['reviewed', 'accepted', 'submitted', 'late', 'revision_requested'];
    $assignmentByClass = [];
    foreach ($allAssignments as $assignment) {
        foreach ($classes as $classCode => $class) {
            if ((int) $assignment['class_id'] === $class['id']) {
                $assignmentByClass[$classCode][] = $assignment;
                break;
            }
        }
    }
    foreach ($assignmentByClass as $classCode => $assignmentsForClass) {
        $class = $classes[$classCode];
        foreach ($assignmentsForClass as $assignment) {
            foreach ($enrollments[$classCode] as $enrollIndex => $enrollment) {
                if (in_array($enrollment['status'], ['withdrawn', 'cancelled'], true)) {
                    continue;
                }
                if (((int) $assignment['id'] + $enrollIndex) % 5 === 4) {
                    continue;
                }
                $existingSubmission = seed_find($db, 'submissions', [
                    'assignment_id' => (int) $assignment['id'],
                    'enrollment_id' => $enrollment['id'],
                ]);
                if ($existingSubmission !== null) {
                    $submissionId = (int) $existingSubmission['id'];
                } else {
                    $status = $submissionStatuses[((int) $assignment['id'] + $enrollIndex) % count($submissionStatuses)];
                    $dueAt = (string) $assignment['due_at'];
                    $submittedAt = $status === 'late'
                        ? seed_add_days(substr($dueAt, 0, 10), 1) . ' 20:30:00'
                        : seed_add_days(substr($dueAt, 0, 10), -1) . ' 20:15:00';
                    $score = null;
                    $feedback = '';
                    $reviewedBy = null;
                    $reviewedAt = null;
                    $revisionCount = 0;
                    if (in_array($status, ['reviewed', 'accepted', 'late'], true)) {
                        $score = 78 + (($enrollment['studentId'] * 3 + (int) $assignment['id']) % 18);
                        $feedback = 'Jawaban sudah baik. Perhatikan kembali bagian analisis dan sertakan contoh yang lebih spesifik.';
                        $reviewedBy = $class['instructorId'];
                        $reviewedAt = seed_add_days(substr($dueAt, 0, 10), 2) . ' 10:00:00';
                    } elseif ($status === 'revision_requested') {
                        $feedback = 'Mohon lengkapi bagian kesimpulan dan daftar referensi, lalu kumpulkan kembali.';
                        $reviewedBy = $class['instructorId'];
                        $reviewedAt = seed_add_days(substr($dueAt, 0, 10), 2) . ' 10:00:00';
                        $revisionCount = 1;
                    }
                    $submissionId = seed_insert($db, 'submissions', [
                        'assignment_id' => (int) $assignment['id'],
                        'enrollment_id' => $enrollment['id'],
                        'student_id' => $enrollment['studentId'],
                        'status' => $status,
                        'text_answer' => 'Berikut jawaban saya untuk ' . $assignment['title'] . '. Saya telah mempelajari materi terkait dan menyusun jawaban berdasarkan pengalaman serta referensi kelas.',
                        'submitted_at' => $submittedAt,
                        'is_late' => $status === 'late' ? 1 : 0,
                        'score' => $score,
                        'feedback' => $feedback,
                        'reviewed_by' => $reviewedBy,
                        'reviewed_at' => $reviewedAt,
                        'revision_count' => $revisionCount,
                        'created_at' => $submittedAt,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    seed_bump('submissions');
                }

                // Berkas jawaban untuk tugas unggah berkas.
                if ((int) $assignment['require_file'] === 1) {
                    $fileName = 'jawaban-' . $enrollment['studentId'] . '.pdf';
                    [, $fileCreated] = seed_ensure($db, 'submission_files', [
                        'submission_id' => $submissionId,
                        'file_name' => $fileName,
                    ], [
                        'submission_id' => $submissionId,
                        'file_name' => $fileName,
                        'file_path' => '/uploads/submission/' . $submissionId . '/' . $fileName,
                        'file_size' => 524288,
                        'mime_type' => 'application/pdf',
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    if ($fileCreated) {
                        seed_bump('submission_files');
                    }
                }

                // Riwayat review singkat bila belum ada.
                $reviewCountStmt = $db->prepare('SELECT COUNT(*) FROM submission_reviews WHERE submission_id = ?');
                $reviewCountStmt->execute([$submissionId]);
                if ((int) $reviewCountStmt->fetchColumn() === 0) {
                    $submissionRow = seed_find($db, 'submissions', ['id' => $submissionId]);
                    $status = $submissionRow ? (string) $submissionRow['status'] : 'submitted';
                    seed_insert($db, 'submission_reviews', [
                        'submission_id' => $submissionId,
                        'reviewer_id' => $enrollment['studentId'],
                        'action' => 'submit',
                        'score' => null,
                        'note' => 'Mengumpulkan tugas melalui portal ELIPS.',
                        'created_at' => $submissionRow ? (string) $submissionRow['submitted_at'] : gmdate('Y-m-d H:i:s'),
                    ]);
                    seed_bump('submission_reviews');
                    if (in_array($status, ['reviewed', 'accepted', 'late'], true)) {
                        seed_insert($db, 'submission_reviews', [
                            'submission_id' => $submissionId,
                            'reviewer_id' => $class['instructorId'],
                            'action' => 'score',
                            'score' => $submissionRow ? $submissionRow['score'] : null,
                            'note' => $submissionRow ? (string) $submissionRow['feedback'] : 'Nilai diberikan instruktur.',
                            'created_at' => $submissionRow && $submissionRow['reviewed_at'] ? (string) $submissionRow['reviewed_at'] : gmdate('Y-m-d H:i:s'),
                        ]);
                        seed_bump('submission_reviews');
                    }
                    if ($status === 'accepted') {
                        seed_insert($db, 'submission_reviews', [
                            'submission_id' => $submissionId,
                            'reviewer_id' => $class['instructorId'],
                            'action' => 'accept',
                            'score' => $submissionRow ? $submissionRow['score'] : null,
                            'note' => 'Tugas diterima sebagai bukti pembelajaran.',
                            'created_at' => gmdate('Y-m-d H:i:s'),
                        ]);
                        seed_bump('submission_reviews');
                    } elseif ($status === 'revision_requested') {
                        seed_insert($db, 'submission_reviews', [
                            'submission_id' => $submissionId,
                            'reviewer_id' => $class['instructorId'],
                            'action' => 'revision',
                            'score' => null,
                            'note' => $submissionRow ? (string) $submissionRow['feedback'] : 'Mohon revisi jawaban.',
                            'created_at' => $submissionRow && $submissionRow['reviewed_at'] ? (string) $submissionRow['reviewed_at'] : gmdate('Y-m-d H:i:s'),
                        ]);
                        seed_bump('submission_reviews');
                    }
                }
            }
        }
    }
    seed_out('Tugas, rubrik, submission, berkas, dan review siap');

    // ============================================================
    // 14. UJIAN, PESERTA, DAN HASIL UJIAN
    // ============================================================
    $examSeeds = [
        [
            'class' => 'ANG-2026-KOM-01', 'title' => 'Ujian Akhir: Komunikasi Efektif',
            'type' => 'written', 'status' => 'published', 'date' => '2026-11-10', 'start' => '09:00:00',
            'duration' => 90, 'weight' => 40,
            'description' => 'Evaluasi pemahaman komunikasi verbal, non-verbal, mendengarkan aktif, dan negosiasi.',
            'instructions' => 'Kerjakan 25 soal pilihan ganda dan 2 soal esai. Buku tertutup.',
        ],
        [
            'class' => 'ANG-2026-ADM-01', 'title' => 'Ujian Praktik: Kearsipan Digital',
            'type' => 'practical', 'status' => 'published', 'date' => '2026-11-12', 'start' => '08:00:00',
            'duration' => 120, 'weight' => 40,
            'description' => 'Peserta menyelesaikan studi kasus kearsipan digital dan surat menyurat.',
            'instructions' => 'Selesaikan studi kasus, unggah berkas hasil praktik, lalu presentasikan selama 10 menit.',
        ],
        [
            'class' => 'ANG-2026-MSO-01', 'title' => 'Ujian Akhir: Microsoft Office',
            'type' => 'practical', 'status' => 'published', 'date' => '2026-10-05', 'start' => '09:00:00',
            'duration' => 120, 'weight' => 40,
            'description' => 'Ujian praktik Word, Excel, dan PowerPoint tingkat dasar.',
            'instructions' => 'Kerjakan tiga studi kasus aplikasi perkantoran secara mandiri.',
        ],
        [
            'class' => 'ANG-2026-MNJ-02', 'title' => 'Ujian Tengah Semester: Manajemen Dasar',
            'type' => 'written', 'status' => 'draft', 'date' => '2026-12-10', 'start' => '18:30:00',
            'duration' => 90, 'weight' => 40,
            'description' => 'Evaluasi konsep dasar manajemen untuk kelas malam.',
            'instructions' => 'Kerjakan 30 soal pilihan ganda dan 2 soal esai.',
        ],
        [
            'class' => 'ANG-2026-MSO-02', 'title' => 'Ujian Praktik: Format Dokumen',
            'type' => 'practical', 'status' => 'draft', 'date' => '2026-12-15', 'start' => '09:00:00',
            'duration' => 90, 'weight' => 40,
            'description' => 'Praktik memformat dokumen dan presentasi profesional.',
            'instructions' => 'Susun dokumen dan presentasi sesuai instruksi penguji.',
        ],
    ];
    foreach ($examSeeds as $seed) {
        $classCode = (string) $seed['class'];
        $class = $classes[$classCode];
        [$examId, $created] = seed_ensure($db, 'exams', [
            'class_id' => $class['id'],
            'title' => (string) $seed['title'],
        ], [
            'class_id' => $class['id'],
            'curriculum_version_id' => $class['versionId'],
            'title' => (string) $seed['title'],
            'description' => (string) $seed['description'],
            'type' => (string) $seed['type'],
            'exam_date' => (string) $seed['date'],
            'start_time' => (string) $seed['start'],
            'duration_minutes' => (int) $seed['duration'],
            'max_score' => 100,
            'weight' => (int) $seed['weight'],
            'instructions' => (string) $seed['instructions'],
            'status' => (string) $seed['status'],
            'created_by' => $class['instructorId'],
            'updated_by' => $class['instructorId'],
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($created) {
            seed_bump('exams');
        }
    }

    $examStmt = $db->query('SELECT * FROM exams ORDER BY id ASC');
    $allExams = $examStmt->fetchAll();
    foreach ($allExams as $exam) {
        $examClassCode = null;
        foreach ($classes as $classCode => $class) {
            if ((int) $exam['class_id'] === $class['id']) {
                $examClassCode = $classCode;
                break;
            }
        }
        if ($examClassCode === null) {
            continue;
        }
        $class = $classes[$examClassCode];
        $isPast = (string) $exam['exam_date'] !== '' && (string) $exam['exam_date'] <= $today;
        foreach ($enrollments[$examClassCode] as $enrollIndex => $enrollment) {
            $participantStatus = 'registered';
            if ($isPast && $exam['status'] === 'published') {
                $participantStatus = ($enrollIndex + (int) $exam['id']) % 7 === 6 ? 'absent' : 'attended';
            }
            [, $participantCreated] = seed_ensure($db, 'exam_participants', [
                'exam_id' => (int) $exam['id'],
                'enrollment_id' => $enrollment['id'],
            ], [
                'exam_id' => (int) $exam['id'],
                'enrollment_id' => $enrollment['id'],
                'student_id' => $enrollment['studentId'],
                'status' => $participantStatus,
                'note' => $participantStatus === 'absent' ? 'Tidak hadir tanpa keterangan.' : '',
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($participantCreated) {
                seed_bump('exam_participants');
            }

            $resultStatus = 'not_assessed';
            $score = null;
            $gradedBy = null;
            $gradedAt = null;
            $note = '';
            if ($isPast && $exam['status'] === 'published') {
                if ($participantStatus === 'attended') {
                    $resultStatus = 'graded';
                    $score = 72 + (($enrollment['studentId'] * 5 + (int) $exam['id']) % 24);
                    $gradedBy = $class['instructorId'];
                    $gradedAt = seed_add_days((string) $exam['exam_date'], 3) . ' 10:00:00';
                    $note = 'Hasil ujian telah diperiksa dan dinilai.';
                } else {
                    $resultStatus = 'missing';
                    $note = 'Tidak hadir sehingga nilai tidak tersedia.';
                }
            }
            [, $resultCreated] = seed_ensure($db, 'exam_results', [
                'exam_id' => (int) $exam['id'],
                'enrollment_id' => $enrollment['id'],
            ], [
                'exam_id' => (int) $exam['id'],
                'enrollment_id' => $enrollment['id'],
                'student_id' => $enrollment['studentId'],
                'score' => $score,
                'status' => $resultStatus,
                'graded_by' => $gradedBy,
                'graded_at' => $gradedAt,
                'note' => $note,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($resultCreated) {
                seed_bump('exam_results');
            }
        }
    }
    seed_out('Ujian, peserta, dan hasil ujian siap');

    // ============================================================
    // 15. PREDIKAT NILAI
    // ============================================================
    $predicateSeeds = [
        ['A', 'Sangat Baik', 90, 100, 1],
        ['A-', 'Sangat Baik', 80, 89.99, 2],
        ['B+', 'Baik', 75, 79.99, 3],
        ['B', 'Baik', 70, 74.99, 4],
        ['B-', 'Baik', 65, 69.99, 5],
        ['C+', 'Cukup', 60, 64.99, 6],
        ['C', 'Cukup', 55, 59.99, 7],
        ['D', 'Kurang', 50, 54.99, 8],
        ['E', 'Tidak Lulus', 0, 49.99, 9],
    ];
    foreach ($predicateSeeds as [$letter, $label, $minScore, $maxScore, $sortOrder]) {
        [, $created] = seed_ensure($db, 'predicate_ranges', ['letter' => $letter], [
            'letter' => $letter,
            'label' => $label,
            'min_score' => $minScore,
            'max_score' => $maxScore,
            'sort_order' => $sortOrder,
            'is_active' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($created) {
            seed_bump('predicate_ranges');
        }
    }
    seed_out('Predikat nilai siap: ' . count($predicateSeeds));

    // ============================================================
    // 16. SKEMA PENILAIAN SETIAP KELAS
    // ============================================================
    $schemes = [];
    foreach ($classes as $classCode => $class) {
        $schemeRow = seed_find($db, 'grade_schemes', ['class_id' => $class['id']]);
        if ($schemeRow === null) {
            $schemeId = seed_insert($db, 'grade_schemes', [
                'course_id' => $class['courseId'],
                'class_id' => $class['id'],
                'name' => 'Skema Penilaian — ' . $class['name'],
                'status' => 'published',
                'pass_score' => 60,
                'rounding' => 'half_up',
                'notes' => 'Bobot demo: tugas 30%, ujian 40%, praktik & partisipasi 30%.',
                'created_by' => $adminId,
                'updated_by' => $adminId,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            seed_bump('grade_schemes');

            $componentSeeds = [
                ['assignment', 'Tugas', 'Rata-rata nilai tugas kelas.', 30, 'weighted', 1],
                ['exam', 'Ujian', 'Rata-rata nilai ujian kelas.', 40, 'weighted', 2],
                ['manual', 'Praktik & Partisipasi', 'Penilaian praktik dan partisipasi langsung dari instruktur.', 30, 'average', 3],
            ];
            foreach ($componentSeeds as [$type, $name, $description, $weight, $aggregation, $sortOrder]) {
                seed_insert($db, 'grade_scheme_components', [
                    'scheme_id' => $schemeId,
                    'type' => $type,
                    'name' => $name,
                    'description' => $description,
                    'weight' => $weight,
                    'aggregation' => $aggregation,
                    'max_score' => 100,
                    'is_required' => 1,
                    'sort_order' => $sortOrder,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                seed_bump('grade_scheme_components');
            }
            $schemeRow = seed_find($db, 'grade_schemes', ['id' => $schemeId]);
        }
        $schemes[$classCode] = $schemeRow;
    }
    seed_out('Skema penilaian siap: ' . count($schemes) . ' kelas');

    // Nilai komponen manual untuk seluruh peserta aktif/tersedia.
    foreach ($schemes as $classCode => $schemeRow) {
        $components = elips_grade_scheme_components((int) $schemeRow['id']);
        foreach ($components as $component) {
            if ($component['type'] !== 'manual') {
                continue;
            }
            foreach ($enrollments[$classCode] as $enrollIndex => $enrollment) {
                if (in_array($enrollment['status'], ['withdrawn', 'cancelled'], true)) {
                    continue;
                }
                $manualScore = 70 + (($enrollment['studentId'] * 7 + (int) $component['id'] * 3 + $enrollIndex * 2) % 27);
                [, $created] = seed_ensure($db, 'grade_component_scores', [
                    'component_id' => (int) $component['id'],
                    'enrollment_id' => $enrollment['id'],
                ], [
                    'component_id' => (int) $component['id'],
                    'enrollment_id' => $enrollment['id'],
                    'student_id' => $enrollment['studentId'],
                    'score' => $manualScore,
                    'status' => 'graded',
                    'assessor_id' => $classes[$classCode]['instructorId'],
                    'assessed_at' => gmdate('Y-m-d H:i:s'),
                    'note' => 'Penilaian praktik dan partisipasi dari instruktur kelas.',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($created) {
                    seed_bump('grade_component_scores');
                }
            }
        }
    }
    seed_out('Nilai komponen manual siap');

    // Tambahkan ujian untuk kelas unggulan agar komponen ujian terisi.
    $manajemenClass = $classes['ANG-2026-MNJ-01'];
    [$ujianManajemenId, $ujianCreated] = seed_ensure($db, 'exams', [
        'class_id' => $manajemenClass['id'],
        'title' => 'Ujian Akhir: Manajemen Dasar',
    ], [
        'class_id' => $manajemenClass['id'],
        'curriculum_version_id' => $manajemenClass['versionId'],
        'title' => 'Ujian Akhir: Manajemen Dasar',
        'description' => 'Evaluasi komprehensif materi manajemen dasar, kepemimpinan, dan pengendalian.',
        'type' => 'written',
        'exam_date' => '2026-11-05',
        'start_time' => '09:00:00',
        'duration_minutes' => 90,
        'max_score' => 100,
        'weight' => 40,
        'instructions' => 'Kerjakan 30 soal pilihan ganda dan 2 soal esai. Buku tertutup.',
        'status' => 'published',
        'created_by' => $manajemenClass['instructorId'],
        'updated_by' => $manajemenClass['instructorId'],
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ]);
    if ($ujianCreated) {
        seed_bump('exams');
    }

    // Lengkapi submission dan hasil ujian pada kelas yang akan dinilai.
    $gradeableClasses = ['ANG-2026-MNJ-01', 'ANG-2026-KOM-01', 'ANG-2026-ADM-01', 'ANG-2026-MSO-01'];
    foreach ($gradeableClasses as $classCode) {
        $class = $classes[$classCode];
        $assignmentStmt = $db->prepare(
            "SELECT * FROM assignments WHERE class_id = ? AND status IN ('published', 'closed') ORDER BY id ASC"
        );
        $assignmentStmt->execute([$class['id']]);
        $classAssignments = $assignmentStmt->fetchAll();

        $examStmt = $db->prepare(
            "SELECT * FROM exams WHERE class_id = ? AND status IN ('published', 'closed') ORDER BY id ASC"
        );
        $examStmt->execute([$class['id']]);
        $classExams = $examStmt->fetchAll();

        foreach ($enrollments[$classCode] as $enrollment) {
            if (in_array($enrollment['status'], ['withdrawn', 'cancelled', 'pending'], true)) {
                continue;
            }

            foreach ($classAssignments as $activity) {
                $activityScore = 74 + (($enrollment['studentId'] * 3 + (int) $activity['id'] * 5) % 23);
                $submission = seed_find($db, 'submissions', [
                    'assignment_id' => (int) $activity['id'],
                    'enrollment_id' => $enrollment['id'],
                ]);
                $dueDate = $activity['due_at'] ? substr((string) $activity['due_at'], 0, 10) : $today;
                if ($submission === null) {
                    seed_insert($db, 'submissions', [
                        'assignment_id' => (int) $activity['id'],
                        'enrollment_id' => $enrollment['id'],
                        'student_id' => $enrollment['studentId'],
                        'status' => 'reviewed',
                        'text_answer' => 'Jawaban lengkap untuk ' . $activity['title'] . ' beserta analisis dan referensi pendukung.',
                        'submitted_at' => seed_add_days($dueDate, -1) . ' 20:15:00',
                        'is_late' => 0,
                        'score' => $activityScore,
                        'feedback' => 'Tugas telah diperiksa. Analisis sudah baik dan sesuai rubrik penilaian.',
                        'reviewed_by' => $class['instructorId'],
                        'reviewed_at' => seed_add_days($dueDate, 1) . ' 09:00:00',
                        'revision_count' => 0,
                        'created_at' => seed_add_days($dueDate, -1) . ' 20:15:00',
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    seed_bump('submissions');
                } elseif ($submission['score'] === null || in_array($submission['status'], ['submitted', 'late', 'revision_requested'], true)) {
                    $db->prepare(
                        "UPDATE submissions
                         SET score = ?, status = 'reviewed', feedback = ?,
                             reviewed_by = ?, reviewed_at = COALESCE(reviewed_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP()
                         WHERE id = ?"
                    )->execute([
                        $activityScore,
                        'Tugas telah diperiksa. Analisis sudah baik dan sesuai rubrik penilaian.',
                        $class['instructorId'],
                        (int) $submission['id'],
                    ]);
                }
            }

            foreach ($classExams as $exam) {
                $examScore = 72 + (($enrollment['studentId'] * 5 + (int) $exam['id'] * 3) % 25);
                $examDate = (string) $exam['exam_date'] !== '' ? (string) $exam['exam_date'] : $today;

                [, $participantCreated] = seed_ensure($db, 'exam_participants', [
                    'exam_id' => (int) $exam['id'],
                    'enrollment_id' => $enrollment['id'],
                ], [
                    'exam_id' => (int) $exam['id'],
                    'enrollment_id' => $enrollment['id'],
                    'student_id' => $enrollment['studentId'],
                    'status' => 'attended',
                    'note' => 'Peserta hadir dan mengerjakan ujian.',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($participantCreated) {
                    seed_bump('exam_participants');
                } else {
                    $db->prepare("UPDATE exam_participants SET status = 'attended', note = 'Peserta hadir dan mengerjakan ujian.', updated_at = UTC_TIMESTAMP() WHERE exam_id = ? AND enrollment_id = ?")
                        ->execute([(int) $exam['id'], $enrollment['id']]);
                }

                $result = seed_find($db, 'exam_results', [
                    'exam_id' => (int) $exam['id'],
                    'enrollment_id' => $enrollment['id'],
                ]);
                if ($result === null) {
                    seed_insert($db, 'exam_results', [
                        'exam_id' => (int) $exam['id'],
                        'enrollment_id' => $enrollment['id'],
                        'student_id' => $enrollment['studentId'],
                        'score' => $examScore,
                        'status' => 'graded',
                        'graded_by' => $class['instructorId'],
                        'graded_at' => seed_add_days($examDate, 3) . ' 10:00:00',
                        'note' => 'Hasil ujian telah diperiksa dan dinilai.',
                        'created_at' => gmdate('Y-m-d H:i:s'),
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    seed_bump('exam_results');
                } else {
                    $db->prepare(
                        "UPDATE exam_results
                         SET score = ?, status = 'graded', graded_by = ?, graded_at = COALESCE(graded_at, UTC_TIMESTAMP()),
                             note = 'Hasil ujian telah diperiksa dan dinilai.', updated_at = UTC_TIMESTAMP()
                         WHERE id = ?"
                    )->execute([$examScore, $class['instructorId'], (int) $result['id']]);
                }
            }
        }
    }
    seed_out('Prasyarat nilai (tugas dan ujian) siap');

    // ============================================================
    // 17. NILAI AKHIR PESERTA
    // ============================================================
    $gradeRecords = [];
    $gradeNow = gmdate('Y-m-d H:i:s');
    foreach ($gradeableClasses as $classCode) {
        $class = $classes[$classCode];
        $schemeRow = $schemes[$classCode];
        foreach ($enrollments[$classCode] as $enrollment) {
            if (in_array($enrollment['status'], ['withdrawn', 'cancelled', 'pending'], true)) {
                continue;
            }
            $enrollmentRow = seed_find($db, 'enrollments', ['id' => $enrollment['id']]);
            if ($enrollmentRow === null) {
                continue;
            }
            $computed = elips_grade_compute_enrollment($enrollmentRow, $schemeRow);
            $finalScore = $computed['finalScore'];
            $letter = $computed['letter'] ?? 'E';
            $isFinal = $finalScore !== null && count($computed['missing']) === 0;
            $scoreInt = $finalScore === null ? 0 : max(0, min(100, (int) round((float) $finalScore)));
            $snapshot = json_encode($computed['components'], JSON_UNESCAPED_UNICODE);
            [$gradeId, $created] = seed_ensure($db, 'grades', ['enrollment_id' => $enrollment['id']], [
                'enrollment_id' => $enrollment['id'],
                'scheme_id' => (int) $schemeRow['id'],
                'course_id' => $class['courseId'],
                'student_id' => $enrollment['studentId'],
                'instructor_id' => $class['instructorId'],
                'score' => $scoreInt,
                'final_score' => $finalScore,
                'grade' => $letter,
                'status' => $isFinal ? 'finalized' : 'incomplete',
                'components_snapshot' => $snapshot,
                'note' => 'Nilai dihasilkan otomatis dari komponen tugas, ujian, dan penilaian manual.',
                'finalized_by' => $isFinal ? $class['instructorId'] : null,
                'finalized_at' => $isFinal ? $gradeNow : null,
                'computed_at' => $gradeNow,
                'created_at' => $gradeNow,
                'updated_at' => $gradeNow,
            ]);
            if ($created) {
                seed_bump('grades');
            }
            $gradeRecords[] = [
                'id' => $gradeId,
                'classCode' => $classCode,
                'enrollmentId' => $enrollment['id'],
                'studentId' => $enrollment['studentId'],
                'courseId' => $class['courseId'],
                'instructorId' => $class['instructorId'],
                'score' => $finalScore === null ? 0.0 : (float) $finalScore,
                'letter' => $letter,
                'status' => $isFinal ? 'finalized' : 'incomplete',
            ];
        }
    }
    $finalizedGrades = array_values(array_filter($gradeRecords, function ($grade) {
        return $grade['status'] === 'finalized';
    }));
    seed_out('Nilai akhir siap: ' . count($gradeRecords) . ' baris, ' . count($finalizedGrades) . ' final');

    // ============================================================
    // 18. KOREKSI NILAI
    // ============================================================
    $correctionTargets = [];
    foreach ($finalizedGrades as $grade) {
        if (!isset($correctionTargets[$grade['classCode']]) && count($correctionTargets) < 4) {
            $correctionTargets[$grade['classCode']] = $grade;
        }
    }
    $correctionTargets = array_values($correctionTargets);
    foreach ($correctionTargets as $index => $grade) {
        $gradeRow = seed_find($db, 'grades', ['id' => $grade['id']]);
        if ($gradeRow === null) {
            continue;
        }
        $previousScore = $gradeRow['final_score'] !== null ? (float) $gradeRow['final_score'] : (float) $gradeRow['score'];
        $newScore = min(100, round($previousScore + 2 + ($index % 3), 2));
        $reason = 'Koreksi nilai demo #' . ($index + 1) . ': verifikasi ulang komponen penilaian dan bukti tugas.';
        $existingCorrection = seed_find($db, 'grade_corrections', [
            'grade_id' => $grade['id'],
            'reason' => $reason,
        ]);
        if ($existingCorrection !== null) {
            continue;
        }
        seed_insert($db, 'grade_corrections', [
            'grade_id' => $grade['id'],
            'enrollment_id' => $grade['enrollmentId'],
            'previous_score' => $previousScore,
            'new_score' => $newScore,
            'previous_letter' => (string) $gradeRow['grade'],
            'new_letter' => elips_predicate_letter($newScore),
            'reason' => $reason,
            'actor_id' => $adminId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        seed_bump('grade_corrections');
        $db->prepare(
            'UPDATE grades SET score = ?, final_score = ?, grade = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
        )->execute([
            max(0, min(100, (int) round($newScore))),
            $newScore,
            elips_predicate_letter($newScore),
            $grade['id'],
        ]);
        seed_audit(
            $db,
            'correct',
            'grade',
            $grade['id'],
            'Nilai #' . $grade['id'],
            $reason,
            $adminId
        );
    }
    seed_out('Koreksi nilai siap: ' . count($correctionTargets) . ' baris');

    // ============================================================
    // 19. SERTIFIKAT KELULUSAN
    // ============================================================
    $publishedCertificates = [];
    $graduatedClass = $classes['ANG-2026-MSO-01'];
    $certificateIndex = 0;
    foreach ($enrollments['ANG-2026-MSO-01'] as $enrollment) {
        if ($enrollment['status'] !== 'completed') {
            continue;
        }
        $gradeRow = seed_find($db, 'grades', ['enrollment_id' => $enrollment['id']]);
        if ($gradeRow === null) {
            continue;
        }
        $certificateIndex++;
        // Format kanonik sama dengan utils.js / elips_generate_certificate_number():
        // CERT-<tahun>-<6 heksadesimal besar>.
        $certificateNumber = 'CERT-' . $currentYear . '-'
            . strtoupper(substr(md5('elips-seed-certificate-' . $certificateIndex), 0, 6));
        $certificateStatus = $certificateIndex === 5 ? 'draft' : 'publish';
        $issuedAt = seed_add_days($graduatedClass['periodEnd'], 2) . ' 10:00:00';
        [$certificateId, $created] = seed_ensure($db, 'certificates', ['enrollment_id' => $enrollment['id']], [
            'student_id' => $enrollment['studentId'],
            'course_id' => $graduatedClass['courseId'],
            'enrollment_id' => $enrollment['id'],
            'certificate_number' => $certificateNumber,
            'status' => $certificateStatus,
            'grade' => (string) $gradeRow['grade'],
            'score' => max(0, min(100, (int) round((float) ($gradeRow['final_score'] ?? $gradeRow['score'])))),
            'issued_by_instructor_at' => $issuedAt,
            'approved_by_admin_id' => $certificateStatus === 'publish' ? $adminId : null,
            'approved_at' => $certificateStatus === 'publish' ? seed_add_days($graduatedClass['periodEnd'], 3) . ' 09:00:00' : null,
            'created_at' => $issuedAt,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($created) {
            seed_bump('certificates');
        }

        // Migrasi data seed lama yang memakai format CERT-2026-MSO-### agar
        // konsisten dengan generator sertifikat aplikasi.
        $certificateRow = seed_find($db, 'certificates', ['id' => $certificateId]);
        $oldCertificateNumber = $certificateRow ? (string) $certificateRow['certificate_number'] : '';
        if ($oldCertificateNumber !== ''
            && $oldCertificateNumber !== $certificateNumber
            && preg_match('/^CERT-\d{4}-MSO-\d{3}$/', $oldCertificateNumber)) {
            $db->prepare('UPDATE certificates SET certificate_number = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$certificateNumber, $certificateId]);
            $db->prepare(
                "UPDATE audit_logs SET entity_label = ?, detail = REPLACE(detail, ?, ?)
                 WHERE entity = 'certificate' AND entity_id = ? AND (entity_label = ? OR detail LIKE ?)"
            )->execute([
                $certificateNumber,
                $oldCertificateNumber,
                $certificateNumber,
                $certificateId,
                $oldCertificateNumber,
                '%' . $oldCertificateNumber . '%',
            ]);
            $db->prepare(
                "UPDATE notifications SET subject = REPLACE(subject, ?, ?), entity_label = ?
                 WHERE entity = 'certificate' AND entity_id = ? AND (entity_label = ? OR subject LIKE ?)"
            )->execute([
                $oldCertificateNumber,
                $certificateNumber,
                $certificateNumber,
                $certificateId,
                $oldCertificateNumber,
                '%' . $oldCertificateNumber . '%',
            ]);
            seed_out('Nomor sertifikat dinormalisasi: ' . $oldCertificateNumber . ' -> ' . $certificateNumber);
        }

        if ($certificateStatus === 'publish') {
            $publishedCertificates[] = [
                'id' => $certificateId,
                'studentId' => $enrollment['studentId'],
                'certificateNumber' => $certificateNumber,
            ];
            seed_audit(
                $db,
                'publish',
                'certificate',
                $certificateId,
                $certificateNumber,
                'Menerbitkan sertifikat kelulusan ' . $certificateNumber . '.',
                $adminId
            );
        }
    }
    seed_out('Sertifikat siap: ' . $certificateIndex . ' baris');

    // ============================================================
    // 20. SURAT MENYURAT TAMBAHAN
    // ============================================================
    $letterSeeds = [
        ['masuk', '102/ELIPS-IN/XI/2026', 'Permohonan Jadwal Pelatihan Korporat', 'PT Nusantara Digital', 'ELIPS LMS', '2026-11-03', '2026-11-04', 'Permohonan', 'penting', 'diproses', 'Permohonan pelatihan untuk 25 karyawan pada Desember 2026.'],
        ['keluar', '091/ELIPS-OUT/XI/2026', 'Penawaran Program Pelatihan Triwulan I 2027', 'ELIPS LMS', 'PT Sinar Abadi', '2026-11-05', '2026-11-06', 'Penawaran', 'biasa', 'selesai', 'Penawaran paket pelatihan komunikasi dan manajemen untuk awal tahun 2027.'],
        ['masuk', '117/SEKOLAH/XI/2026', 'Undangan Pameran Pendidikan Akhir Tahun', 'Dinas Pendidikan Kota', 'ELIPS LMS', '2026-11-08', '2026-11-09', 'Undangan', 'segera', 'baru', 'Undangan partisipasi pameran pendidikan pada 5-7 Desember 2026.'],
        ['keluar', '095/ELIPS-OUT/XI/2026', 'Laporan Evaluasi Program Pelatihan Oktober', 'ELIPS LMS', 'Kepala Dinas Pendidikan', '2026-11-10', '2026-11-11', 'Laporan', 'penting', 'arsip', 'Laporan capaian peserta, evaluasi instruktur, dan rekomendasi perbaikan.'],
    ];
    foreach ($letterSeeds as [$direction, $number, $subject, $sender, $recipient, $letterDate, $handledDate, $category, $classification, $status, $summary]) {
        [$letterId, $created] = seed_ensure($db, 'letters', ['letter_number' => $number], [
            'direction' => $direction,
            'letter_number' => $number,
            'subject' => $subject,
            'sender' => $sender,
            'recipient' => $recipient,
            'letter_date' => $letterDate,
            'handled_date' => $handledDate,
            'category' => $category,
            'classification' => $classification,
            'status' => $status,
            'summary' => $summary,
            'attachment_url' => '/uploads/surat/' . strtolower(str_replace('/', '-', $number)) . '.pdf',
            'notes' => 'Data demo surat menyurat dari seed lengkap.',
            'created_by' => $adminId,
            'created_at' => $letterDate . ' 08:00:00',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($created) {
            seed_bump('letters');
            seed_audit(
                $db,
                'create',
                'letter',
                $letterId,
                $number,
                'Mencatat surat ' . $direction . ' nomor ' . $number . ' — ' . $subject . '.',
                $adminId
            );
        }
    }
    seed_out('Surat menyurat tambahan siap: ' . count($letterSeeds));

    // ============================================================
    // 21. MARKETING: CAMPAIGN, LEAD, FOLLOW-UP, TARGET, TASK, CLIENT
    // ============================================================
    $marketingOwners = [];
    foreach ($marketingEmails as $email) {
        if (isset($users[$email])) {
            $marketingOwners[] = $users[$email];
        }
    }

    $campaignStart = $now->modify('first day of this month')->format('Y-m-d');
    $campaignEnd = $now->modify('+45 days')->format('Y-m-d');
    $campaignChannels = ['sosial_media', 'email', 'pameran', 'sekolah', 'komunitas', 'telemarketing', 'lainnya'];
    $leadNamePool = [
        'Rizky Ananda', 'Salsabila Putri', 'Bagus Firmansyah', 'Nadia Ayu',
        'Yoga Saputra', 'Melati Kusuma', 'Arif Hidayat', 'Citra Dewi',
        'Fajar Nugraha', 'Wulan Sari', 'Rendi Pratama', 'Ayu Lestari',
        'Hafiz Alfarizi', 'Nabila Zahra', 'Doni Setiawan', 'Mega Puspita',
        'Iqbal Maulana', 'Sinta Maharani',
    ];
    $leadStatuses = ['baru', 'dihubungi', 'prospek', 'terdaftar', 'batal'];
    $leadSources = ['walk_in', 'referensi', 'sekolah', 'sosial_media', 'pameran', 'telemarketing', 'lainnya'];
    $followupChannels = ['whatsapp', 'telepon', 'email', 'kunjungan', 'lainnya'];
    $taskStatuses = ['belum', 'proses', 'selesai', 'belum'];
    $clientCompanies = [
        'PT Nusantara Digital', 'CV Karya Mandiri', 'Universitas Cendekia',
        'PT Sinar Abadi', 'Dinas Pendidikan Kota', 'PT Teknologi Nusa',
        'Koperasi Sejahtera', 'PT Global Edukasi', 'Yayasan Bina Insan',
    ];
    $clientStatuses = ['prospek', 'aktif', 'nonaktif'];
    $seedLeads = [];

    $previousPeriod = $now->modify('-1 month')->format('Y-m');
    foreach ($marketingOwners as $ownerIndex => $owner) {
        $ownerId = (int) $owner['id'];
        $campaignName = 'Kampanye ' . $now->format('Y') . ' — ' . $owner['name'];
        [$campaignId, $created] = seed_ensure($db, 'marketing_campaigns', [
            'owner_id' => $ownerId,
            'name' => $campaignName,
        ], [
            'owner_id' => $ownerId,
            'name' => $campaignName,
            'channel' => $campaignChannels[$ownerIndex % count($campaignChannels)],
            'status' => $ownerIndex % 4 === 3 ? 'draft' : 'aktif',
            'start_date' => $campaignStart,
            'end_date' => $campaignEnd,
            'budget' => 5000000 + $ownerIndex * 750000,
            'target_leads' => 18 + ($ownerIndex % 5) * 2,
            'target_enrollments' => 4 + ($ownerIndex % 3),
            'notes' => 'Campaign demo untuk akun marketing ' . $owner['name'] . '.',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($created) {
            seed_bump('marketing_campaigns');
        }

        for ($leadIndex = 0; $leadIndex < 2; $leadIndex++) {
            $leadName = $leadNamePool[($ownerIndex * 2 + $leadIndex) % count($leadNamePool)];
            $leadEmail = 'lead.' . $ownerId . '.' . ($leadIndex + 1) . '@example.com';
            $leadStatus = $leadStatuses[($ownerIndex + $leadIndex) % count($leadStatuses)];
            $leadSource = $leadSources[($ownerIndex * 2 + $leadIndex) % count($leadSources)];
            $convertedAt = $leadStatus === 'terdaftar' ? $now->modify('-2 days')->format('Y-m-d H:i:s') : null;
            $nextFollowup = in_array($leadStatus, ['terdaftar', 'batal'], true)
                ? null
                : $now->modify('+' . (2 + $leadIndex) . ' days')->format('Y-m-d');
            [$leadId, $leadCreated] = seed_ensure($db, 'marketing_leads', [
                'owner_id' => $ownerId,
                'email' => $leadEmail,
            ], [
                'owner_id' => $ownerId,
                'campaign_id' => $campaignId,
                'name' => $leadName,
                'phone' => '0813' . str_pad((string) ($ownerId * 10 + $leadIndex), 8, '0', STR_PAD_LEFT),
                'email' => $leadEmail,
                'city' => ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang'][($ownerIndex + $leadIndex) % 5],
                'source' => $leadSource,
                'status' => $leadStatus,
                'interest' => ['Pelatihan Manajemen Dasar', 'Komunikasi Efektif', 'Administrasi Perkantoran', 'Microsoft Office'][($ownerIndex + $leadIndex) % 4],
                'notes' => 'Lead demo dari kampanye ' . $campaignName . '.',
                'next_followup_date' => $nextFollowup,
                'converted_at' => $convertedAt,
                'enrollment_value' => $leadStatus === 'terdaftar' ? 1500000 : 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($leadCreated) {
                seed_bump('marketing_leads');
            }
            $seedLeads[] = [
                'id' => $leadId,
                'ownerId' => $ownerId,
                'name' => $leadName,
                'status' => $leadStatus,
                'campaignId' => $campaignId,
            ];

            $followupDays = [-5, -1];
            foreach ($followupDays as $followupIndex => $offset) {
                $followupDate = $now->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
                $followupNote = 'Follow-up demo ke-' . ($followupIndex + 1) . ' untuk ' . $leadName . ': konfirmasi minat dan jadwal konsultasi.';
                [, $followupCreated] = seed_ensure($db, 'marketing_followups', [
                    'lead_id' => $leadId,
                    'followup_date' => $followupDate,
                    'note' => $followupNote,
                ], [
                    'lead_id' => $leadId,
                    'owner_id' => $ownerId,
                    'channel' => $followupChannels[($ownerIndex + $followupIndex) % count($followupChannels)],
                    'followup_date' => $followupDate,
                    'note' => $followupNote,
                    'outcome' => $followupIndex === 0 ? 'Prospek merespons dan meminta informasi jadwal.' : 'Konfirmasi ulang kebutuhan pelatihan dan estimasi jumlah peserta.',
                    'next_date' => $now->modify('+' . (3 + $followupIndex) . ' days')->format('Y-m-d'),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($followupCreated) {
                    seed_bump('marketing_followups');
                }
            }
        }

        $ownerLeads = array_values(array_filter($seedLeads, function ($lead) use ($ownerId) {
            return $lead['ownerId'] === $ownerId;
        }));
        $primaryLeadName = $ownerLeads[0]['name'] ?? 'prospek baru';
        $taskSeeds = [
            [
                'title' => 'Follow-up prospek ' . $primaryLeadName,
                'type' => 'follow_up',
                'priority' => 'tinggi',
                'status' => $taskStatuses[$ownerIndex % count($taskStatuses)],
                'lead_id' => $ownerLeads[0]['id'] ?? null,
                'due_date' => $now->modify('+' . (1 + $ownerIndex % 3) . ' days')->format('Y-m-d'),
            ],
            [
                'title' => 'Rekap campaign ' . $now->format('F Y'),
                'type' => 'administrasi',
                'priority' => 'sedang',
                'status' => $ownerIndex % 3 === 0 ? 'belum' : 'proses',
                'lead_id' => null,
                'due_date' => $now->modify('+' . (4 + $ownerIndex % 4) . ' days')->format('Y-m-d'),
            ],
        ];
        foreach ($taskSeeds as $taskSeed) {
            [, $taskCreated] = seed_ensure($db, 'marketing_tasks', [
                'owner_id' => $ownerId,
                'title' => $taskSeed['title'],
            ], [
                'owner_id' => $ownerId,
                'campaign_id' => $campaignId,
                'lead_id' => $taskSeed['lead_id'],
                'title' => $taskSeed['title'],
                'description' => 'Tugas demo marketing untuk ' . $owner['name'] . '.',
                'type' => $taskSeed['type'],
                'priority' => $taskSeed['priority'],
                'status' => $taskSeed['status'],
                'due_date' => $taskSeed['due_date'],
                'completed_at' => $taskSeed['status'] === 'selesai' ? $now->modify('-1 day')->format('Y-m-d H:i:s') : null,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($taskCreated) {
                seed_bump('marketing_tasks');
            }
        }

        for ($clientIndex = 0; $clientIndex < 2; $clientIndex++) {
            $clientEmail = 'client.' . $ownerId . '.' . ($clientIndex + 1) . '@example.com';
            $company = $clientCompanies[($ownerIndex * 2 + $clientIndex) % count($clientCompanies)];
            [$clientId, $clientCreated] = seed_ensure($db, 'marketing_clients', [
                'owner_id' => $ownerId,
                'email' => $clientEmail,
            ], [
                'owner_id' => $ownerId,
                'campaign_id' => $campaignId,
                'name' => $company,
                'company' => $company,
                'pic' => $owner['name'],
                'phone' => '021' . str_pad((string) ($ownerId * 10 + $clientIndex), 7, '0', STR_PAD_LEFT),
                'email' => $clientEmail,
                'city' => ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang'][($ownerIndex + $clientIndex) % 5],
                'address' => 'Jl. Demo Korporat No. ' . ($ownerIndex * 2 + $clientIndex + 10),
                'status' => $clientStatuses[($ownerIndex + $clientIndex) % count($clientStatuses)],
                'notes' => 'Klien demo hasil pendataan marketing ' . $owner['name'] . '.',
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($clientCreated) {
                seed_bump('marketing_clients');
                seed_audit(
                    $db,
                    'create',
                    'marketing_client',
                    $clientId,
                    $company,
                    'Menambahkan klien marketing ' . $company . ' untuk ' . $owner['name'] . '.',
                    $adminId
                );
            }
        }

        foreach ([$currentPeriod, $previousPeriod] as $period) {
            $isCurrent = $period === $currentPeriod;
            [, $targetCreated] = seed_ensure($db, 'marketing_targets', [
                'user_id' => $ownerId,
                'period' => $period,
            ], [
                'user_id' => $ownerId,
                'period' => $period,
                'leads_target' => $isCurrent ? 20 : 18,
                'followups_target' => $isCurrent ? 60 : 54,
                'enrollments_target' => $isCurrent ? 5 : 4,
                'revenue_target' => $isCurrent ? 12500000 : 11000000,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($targetCreated) {
                seed_bump('marketing_targets');
            }
        }
    }
    seed_out('Data marketing siap: ' . count($marketingOwners) . ' akun marketing');

    // ============================================================
    // 22. ABSENSI DAN JADWAL SHIFT KARYAWAN
    // ============================================================
    $employees = [];
    foreach ($employeeEmails as $email) {
        if (isset($users[$email])) {
            $employees[] = $users[$email];
        }
    }
    $attendanceStart = $seedAnchor->modify('-9 days');
    for ($dayOffset = 0; $dayOffset <= 9; $dayOffset++) {
        $attendanceDate = $attendanceStart->modify('+' . $dayOffset . ' days');
        if ((int) $attendanceDate->format('N') >= 6) {
            continue;
        }
        $attendanceDateString = $attendanceDate->format('Y-m-d');
        foreach ($employees as $employeeIndex => $employee) {
            $employeeId = (int) $employee['id'];
            $isLate = ($employeeIndex + $dayOffset) % 7 === 3;
            $inAt = $attendanceDate->setTime(8, $isLate ? 35 : ($employeeIndex * 7) % 20, 0)->format('Y-m-d H:i:s');
            $outAt = $attendanceDate->setTime(17, ($employeeIndex * 11) % 45, 0)->format('Y-m-d H:i:s');
            $baseAttendance = [
                'user_id' => $employeeId,
                'attendance_date' => $attendanceDateString,
                'timezone' => 'Asia/Jakarta',
                'photo' => null,
                'photo_mime' => 'image/jpeg',
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
                'accuracy_m' => 12.5,
                'location_label' => 'Kantor Pusat ELIPS, Jakarta',
                'location_source' => 'gps',
                'ip_address' => '10.10.' . (($employeeId % 20) + 1) . '.25',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ELIPS-Seed',
                'platform' => 'Windows 11',
                'language' => 'id-ID',
                'screen' => '1920x1080',
                'device_memory' => 8,
                'hardware_concurrency' => 8,
                'connection_type' => 'wifi',
                'online' => 1,
                'notes' => $isLate ? 'Datang terlambat karena kondisi lalu lintas.' : 'Absensi demo karyawan.',
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];
            [, $inCreated] = seed_ensure($db, 'staff_attendances', [
                'user_id' => $employeeId,
                'attendance_date' => $attendanceDateString,
                'kind' => 'in',
            ], array_merge($baseAttendance, [
                'kind' => 'in',
                'taken_at' => $inAt,
            ]));
            if ($inCreated) {
                seed_bump('staff_attendances');
            }
            [, $outCreated] = seed_ensure($db, 'staff_attendances', [
                'user_id' => $employeeId,
                'attendance_date' => $attendanceDateString,
                'kind' => 'out',
            ], array_merge($baseAttendance, [
                'kind' => 'out',
                'taken_at' => $outAt,
                'notes' => 'Absensi pulang demo karyawan.',
            ]));
            if ($outCreated) {
                seed_bump('staff_attendances');
            }
        }
    }
    seed_out('Absensi karyawan siap: ' . count($employees) . ' karyawan');

    for ($dayOffset = 0; $dayOffset < 8; $dayOffset++) {
        $shiftDate = $seedAnchor->modify('+' . $dayOffset . ' days');
        if ((int) $shiftDate->format('N') >= 6) {
            continue;
        }
        $shiftDateString = $shiftDate->format('Y-m-d');
        foreach ($employees as $employeeIndex => $employee) {
            $shiftType = ['pagi', 'sore', 'custom'][($employeeIndex + $dayOffset) % 3];
            if ($shiftType === 'pagi') {
                $startTime = '08:00:00';
                $endTime = '16:00:00';
            } elseif ($shiftType === 'sore') {
                $startTime = '13:00:00';
                $endTime = '21:00:00';
            } else {
                $startTime = '09:00:00';
                $endTime = '18:00:00';
            }
            [$shiftId, $created] = seed_ensure($db, 'staff_shifts', [
                'user_id' => (int) $employee['id'],
                'shift_date' => $shiftDateString,
            ], [
                'user_id' => (int) $employee['id'],
                'shift_date' => $shiftDateString,
                'shift_type' => $shiftType,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'note' => 'Jadwal shift demo untuk ' . $employee['name'] . '.',
                'created_by' => $adminId,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($created) {
                seed_bump('staff_shifts');
                if ($dayOffset === 0) {
                    seed_audit(
                        $db,
                        'create',
                        'staff_shift',
                        $shiftId,
                        'Shift ' . $shiftDateString . ' — ' . $employee['name'],
                        'Menetapkan jadwal shift ' . $shiftType . ' untuk ' . $employee['name'] . '.',
                        $adminId
                    );
                }
            }
        }
    }
    seed_out('Jadwal shift karyawan siap');

    // ============================================================
    // 23. PERANGKAT LOGIN "INGAT SAYA"
    // ============================================================
    $deviceUsers = [
        'admin@example.com',
        'ahmad@example.com',
        'budi.student@example.com',
        'dewi.karyawan@example.com',
    ];
    foreach ($deviceUsers as $email) {
        if (!isset($users[$email])) {
            continue;
        }
        $deviceUser = $users[$email];
        $tokenActive = hash('sha256', 'elips-seed-device-active-' . $deviceUser['id']);
        [, $created] = seed_ensure($db, 'user_devices', ['token_hash' => $tokenActive], [
            'user_id' => (int) $deviceUser['id'],
            'token_hash' => $tokenActive,
            'device_label' => 'Windows • Chrome',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ELIPS-Seed',
            'ip_address' => '10.10.1.' . (($deviceUser['id'] % 20) + 1),
            'created_at' => $now->modify('-10 days')->format('Y-m-d H:i:s'),
            'last_used_at' => $now->modify('-1 day')->format('Y-m-d H:i:s'),
            'expires_at' => $now->modify('+30 days')->format('Y-m-d H:i:s'),
            'revoked_at' => null,
            'revoked_reason' => '',
        ]);
        if ($created) {
            seed_bump('user_devices');
        }

        $tokenRevoked = hash('sha256', 'elips-seed-device-revoked-' . $deviceUser['id']);
        [, $revokedCreated] = seed_ensure($db, 'user_devices', ['token_hash' => $tokenRevoked], [
            'user_id' => (int) $deviceUser['id'],
            'token_hash' => $tokenRevoked,
            'device_label' => 'Android • Chrome',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14) ELIPS-Seed',
            'ip_address' => '10.10.2.' . (($deviceUser['id'] % 20) + 1),
            'created_at' => $now->modify('-20 days')->format('Y-m-d H:i:s'),
            'last_used_at' => $now->modify('-5 days')->format('Y-m-d H:i:s'),
            'expires_at' => $now->modify('+10 days')->format('Y-m-d H:i:s'),
            'revoked_at' => $now->modify('-4 days')->format('Y-m-d H:i:s'),
            'revoked_reason' => 'logout',
        ]);
        if ($revokedCreated) {
            seed_bump('user_devices');
        }
    }
    seed_out('Perangkat login siap');

    // ============================================================
    // 24. AUDIT LOG TAMBAHAN
    // ============================================================
    foreach ($gradeableClasses as $classCode) {
        $class = $classes[$classCode];
        foreach ($gradeRecords as $grade) {
            if ($grade['classCode'] !== $classCode) {
                continue;
            }
            seed_audit(
                $db,
                'finalize',
                'grade',
                $grade['id'],
                'Nilai akhir ' . $classCode,
                'Memfinalisasi nilai akhir peserta #' . $grade['studentId'] . ' pada kelas ' . $classCode . '.',
                $class['instructorId']
            );
            break;
        }
    }
    seed_audit(
        $db,
        'create',
        'grade_scheme',
        (int) $schemes['ANG-2026-MNJ-01']['id'],
        'Skema Penilaian Pelatihan Manajemen Dasar',
        'Menetapkan skema penilaian kelas Pelatihan Manajemen Dasar.',
        $adminId
    );
    seed_audit(
        $db,
        'publish',
        'assignment',
        (int) ($allAssignments[0]['id'] ?? 0),
        (string) ($allAssignments[0]['title'] ?? 'Tugas kelas'),
        'Mempublikasikan tugas kelas untuk peserta.',
        $adminId
    );
    seed_out('Audit log tambahan siap');

    // ============================================================
    // 25. NOTIFIKASI SIMULASI
    // ============================================================
    $usersById = [];
    foreach ($users as $userEntry) {
        $usersById[$userEntry['id']] = $userEntry;
    }

    foreach ($publishedCertificates as $certificate) {
        $student = $usersById[$certificate['studentId']] ?? null;
        seed_notification($db, [
            'type' => 'certificate_published',
            'recipient_id' => $certificate['studentId'],
            'recipient_name' => $student['name'] ?? 'Peserta',
            'recipient_email' => $student['email'] ?? '',
            'recipient_role' => 'peserta',
            'subject' => 'Sertifikat kelulusan ' . $certificate['certificateNumber'] . ' telah terbit',
            'body' => 'Sertifikat kelulusan Microsoft Office Anda telah diterbitkan dan dapat diunduh melalui portal ELIPS.',
            'entity' => 'certificate',
            'entity_id' => $certificate['id'],
            'entity_label' => $certificate['certificateNumber'],
        ]);
    }

    foreach ($gradeableClasses as $classCode) {
        foreach ($gradeRecords as $grade) {
            if ($grade['classCode'] !== $classCode || $grade['status'] !== 'finalized') {
                continue;
            }
            $student = $usersById[$grade['studentId']] ?? null;
            seed_notification($db, [
                'type' => 'grade_finalized',
                'recipient_id' => $grade['studentId'],
                'recipient_name' => $student['name'] ?? 'Peserta',
                'recipient_email' => $student['email'] ?? '',
                'recipient_role' => 'peserta',
                'subject' => 'Nilai akhir kelas ' . $classCode . ' telah difinalisasi',
                'body' => 'Nilai akhir Anda pada kelas ' . $classCode . ' telah difinalisasi dengan predikat ' . $grade['letter'] . '.',
                'entity' => 'grade',
                'entity_id' => $grade['id'],
                'entity_label' => 'Nilai #' . $grade['id'],
            ]);
            break;
        }
    }

    $rescheduledStmt = $db->prepare(
        "SELECT cs.*, cl.code AS class_code, cl.instructor_id
         FROM class_sessions cs
         JOIN classes cl ON cl.id = cs.class_id
         WHERE cs.status = 'rescheduled'
         ORDER BY cs.id ASC LIMIT 1"
    );
    $rescheduledStmt->execute();
    $rescheduledSession = $rescheduledStmt->fetch() ?: null;
    if ($rescheduledSession !== null) {
        $instructor = $usersById[(int) $rescheduledSession['instructor_id']] ?? null;
        seed_notification($db, [
            'type' => 'class_rescheduled',
            'recipient_id' => (int) $rescheduledSession['instructor_id'],
            'recipient_name' => $instructor['name'] ?? 'Instruktur',
            'recipient_email' => $instructor['email'] ?? '',
            'recipient_role' => 'instruktur',
            'subject' => 'Sesi ' . $rescheduledSession['class_code'] . ' dijadwalkan ulang',
            'body' => 'Sesi pertemuan ' . $rescheduledSession['session_number'] . ' pada kelas ' . $rescheduledSession['class_code'] . ' dijadwalkan ulang. Silakan periksa jadwal pengganti.',
            'entity' => 'class_session',
            'entity_id' => (int) $rescheduledSession['id'],
            'entity_label' => (string) $rescheduledSession['title'],
        ]);
    }

    $letterNotificationStmt = $db->prepare("SELECT * FROM letters WHERE letter_number = ? LIMIT 1");
    $letterNotificationStmt->execute(['117/SEKOLAH/XI/2026']);
    $letterNotification = $letterNotificationStmt->fetch() ?: null;
    if ($letterNotification !== null) {
        seed_notification($db, [
            'type' => 'letter_received',
            'recipient_id' => $adminId,
            'recipient_name' => 'Administrator',
            'recipient_email' => 'admin@example.com',
            'recipient_role' => 'admin',
            'subject' => 'Surat baru: ' . $letterNotification['subject'],
            'body' => 'Surat masuk dari ' . $letterNotification['sender'] . ' dengan nomor ' . $letterNotification['letter_number'] . ' telah dicatat.',
            'entity' => 'letter',
            'entity_id' => (int) $letterNotification['id'],
            'entity_label' => (string) $letterNotification['letter_number'],
        ]);
    }

    if (count($marketingOwners) > 0) {
        $targetOwner = $marketingOwners[0];
        seed_notification($db, [
            'type' => 'marketing_target_reminder',
            'recipient_id' => (int) $targetOwner['id'],
            'recipient_name' => $targetOwner['name'],
            'recipient_email' => $targetOwner['email'],
            'recipient_role' => 'karyawan',
            'subject' => 'Pengingat target marketing ' . $currentPeriod,
            'body' => 'Target lead, follow-up, pendaftaran, dan pendapatan bulan ini sudah tersedia di dashboard marketing.',
            'entity' => 'marketing_target',
            'entity_id' => (int) $targetOwner['id'],
            'entity_label' => 'Target ' . $currentPeriod,
        ]);
    }

    if (count($employees) > 0) {
        $shiftEmployee = $employees[0];
        $shiftStmt = $db->prepare('SELECT * FROM staff_shifts WHERE user_id = ? ORDER BY shift_date ASC LIMIT 1');
        $shiftStmt->execute([(int) $shiftEmployee['id']]);
        $shiftRow = $shiftStmt->fetch() ?: null;
        if ($shiftRow !== null) {
            seed_notification($db, [
                'type' => 'shift_schedule',
                'recipient_id' => (int) $shiftEmployee['id'],
                'recipient_name' => $shiftEmployee['name'],
                'recipient_email' => $shiftEmployee['email'],
                'recipient_role' => 'karyawan',
                'subject' => 'Jadwal shift ' . $shiftRow['shift_date'],
                'body' => 'Anda dijadwalkan shift ' . $shiftRow['shift_type'] . ' pada ' . $shiftRow['shift_date'] . ' pukul ' . $shiftRow['start_time'] . ' sampai ' . $shiftRow['end_time'] . '.',
                'entity' => 'staff_shift',
                'entity_id' => (int) $shiftRow['id'],
                'entity_label' => 'Shift ' . $shiftRow['shift_date'],
            ]);
        }
    }
    seed_out('Notifikasi simulasi siap');

    // ============================================================
    // 26. COMMIT & RINGKASAN
    // ============================================================
    $db->commit();

    seed_out('');
    seed_out('=== RINGKASAN DATA DUMMY ELIPS LMS ===');
    $tables = [];
    foreach ($db->query('SHOW TABLES') as $tableRow) {
        $tables[] = (string) reset($tableRow);
    }
    sort($tables);
    $emptyTables = [];
    $totalRows = 0;
    foreach ($tables as $table) {
        $count = (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        $totalRows += $count;
        printf("%-34s %6d\n", $table, $count);
        if ($count === 0) {
            $emptyTables[] = $table;
        }
    }
    seed_out('');
    seed_out('Total baris seluruh tabel: ' . $totalRows);
    if (count($emptyTables) === 0) {
        seed_out('Semua tabel terisi data dummy.');
    } else {
        seed_out('Tabel masih kosong: ' . implode(', ', $emptyTables));
    }
    if (count($report) > 0) {
        seed_out('');
        seed_out('Baris baru pada eksekusi ini:');
        ksort($report);
        foreach ($report as $table => $count) {
            printf("%-34s %6d\n", $table, $count);
        }
    } else {
        seed_out('Tidak ada baris baru; data sudah lengkap dan idempoten.');
    }
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    seed_out('Seed gagal: ' . $error->getMessage());
    if (PHP_SAPI === 'cli') {
        exit(1);
    }
}
