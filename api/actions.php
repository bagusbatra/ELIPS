<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - aksi API
// Semua aturan bisnis dijalankan di server: hak akses role,
// kepemilikan kursus, penugasan, publikasi, penilaian, dan
// penerbitan sertifikat. Client hanya memanggil aksi.
// ============================================================

function elips_dispatch(string $action, array $payload): array
{
    switch ($action) {
        case 'auth.login':
            return elips_action_auth_login($payload);
        case 'auth.logout':
            return elips_action_auth_logout();
        case 'user.create':
            return elips_action_user_create($payload);
        case 'user.update':
            return elips_action_user_update($payload);
        case 'user.delete':
            return elips_action_user_delete($payload);
        case 'user.assignCourses':
            return elips_action_user_assign_courses($payload);
        case 'course.create':
            return elips_action_course_create($payload);
        case 'course.update':
            return elips_action_course_update($payload);
        case 'course.delete':
            return elips_action_course_delete($payload);
        case 'course.status':
            return elips_action_course_status($payload);
        case 'enrollment.assign':
            return elips_action_enrollment_assign($payload);
        case 'enrollment.status':
            return elips_action_enrollment_status($payload);
        case 'enrollment.delete':
            return elips_action_enrollment_delete($payload);
        case 'module.create':
            return elips_action_module_create($payload);
        case 'module.update':
            return elips_action_module_update($payload);
        case 'module.delete':
            return elips_action_module_delete($payload);
        case 'lesson.create':
            return elips_action_lesson_create($payload);
        case 'lesson.update':
            return elips_action_lesson_update($payload);
        case 'lesson.delete':
            return elips_action_lesson_delete($payload);
        case 'grade.save':
            return elips_action_grade_save($payload);
        case 'certificate.draft':
            return elips_action_certificate_draft($payload);
        case 'certificate.approve':
            return elips_action_certificate_approve($payload);
        case 'progress.complete':
            return elips_action_progress_complete($payload);
        case 'audit.clear':
            return elips_action_audit_clear();
        case 'notification.clear':
            return elips_action_notification_clear();
        default:
            throw new ElipsError('Aksi tidak dikenal: ' . $action);
    }
}

function elips_payload_array(array $payload, string $key): array
{
    return isset($payload[$key]) && is_array($payload[$key]) ? $payload[$key] : [];
}

// ---------- Auth ----------

function elips_action_auth_login(array $p): array
{
    $email = strtolower(trim((string) ($p['email'] ?? '')));
    $password = (string) ($p['password'] ?? '');

    if ($email === '') return elips_fail('Email wajib diisi.');
    if ($password === '') return elips_fail('Password wajib diisi.');

    $user = elips_user_by_email($email);
    if (!$user) return elips_fail('Email tidak terdaftar. Gunakan akun demo yang tersedia.');
    if ($user['status'] === 'nonaktif') return elips_fail('Akun Anda nonaktif. Hubungi administrator.');
    if (!password_verify($password, $user['password_hash'])) return elips_fail('Password salah.');

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['logged_in_at'] = gmdate('c');

    return elips_ok('Login berhasil.', elips_serialize_user($user));
}

function elips_action_auth_logout(): array
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    return elips_ok('Anda telah keluar.');
}

// ---------- Users ----------

function elips_action_user_create(array $p): array
{
    $actor = elips_session_user();
    $name = trim((string) ($p['name'] ?? ''));
    $email = strtolower(trim((string) ($p['email'] ?? '')));
    $phone = trim((string) ($p['phone'] ?? ''));
    $password = (string) ($p['password'] ?? '');
    $role = (string) ($p['role'] ?? 'peserta');
    $status = (string) ($p['status'] ?? 'aktif');

    if ($name === '') return elips_fail('Nama wajib diisi.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return elips_fail('Email tidak valid.');
    if ($password === '') return elips_fail('Password wajib diisi.');
    if (strlen($password) < 8) return elips_fail('Password minimal 8 karakter.');
    if (!in_array($role, ['admin', 'instruktur', 'peserta'], true)) return elips_fail('Role pengguna tidak valid.');
    if (!in_array($status, ['aktif', 'nonaktif'], true)) return elips_fail('Status pengguna tidak valid.');

    $anonymous = $actor === null;
    if ($anonymous) {
        // Pendaftaran mandiri hanya boleh membuat akun peserta aktif.
        if ($role !== 'peserta' || $status !== 'aktif') {
            return elips_fail('Anda tidak berhak menambahkan pengguna dengan role ini.');
        }
    } elseif ($actor['role'] !== 'admin') {
        return elips_fail('Anda tidak berhak menambahkan pengguna.');
    }

    if (elips_user_by_email($email)) {
        return elips_fail('Email sudah digunakan oleh pengguna lain.');
    }

    $stmt = elips_db()->prepare(
        'INSERT INTO users (name, email, phone, password_hash, role, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
    $id = (int) elips_db()->lastInsertId();

    if ($anonymous) {
        $_SESSION['user_id'] = $id;
        $_SESSION['logged_in_at'] = gmdate('c');
    }

    elips_audit(
        'create',
        'user',
        $id,
        $name,
        'Menambahkan pengguna "' . $name . '" dengan role ' . $role . '.',
        $anonymous ? elips_user_row($id) : $actor
    );

    return elips_ok('Pengguna berhasil ditambahkan.', elips_public_user($id));
}

function elips_action_user_update(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $data = elips_payload_array($p, 'data');

    $user = elips_user_row($id);
    if (!$user) return elips_fail('Pengguna tidak ditemukan.');

    $isAdmin = $actor['role'] === 'admin';
    $isSelf = (int) $actor['id'] === $id;
    if (!$isAdmin && !$isSelf) {
        return elips_fail('Anda tidak berhak memperbarui pengguna ini.');
    }

    $nextName = array_key_exists('name', $data) ? trim((string) $data['name']) : $user['name'];
    $nextEmail = array_key_exists('email', $data) ? strtolower(trim((string) $data['email'])) : $user['email'];
    $nextRole = $isAdmin && array_key_exists('role', $data) ? (string) $data['role'] : $user['role'];
    $nextStatus = $isAdmin && array_key_exists('status', $data) ? (string) $data['status'] : $user['status'];
    $nextPhone = array_key_exists('phone', $data) ? trim((string) $data['phone']) : $user['phone'];
    $password = (string) ($data['password'] ?? '');

    if ($nextName === '') return elips_fail('Nama wajib diisi.');
    if (!filter_var($nextEmail, FILTER_VALIDATE_EMAIL)) return elips_fail('Email tidak valid.');
    if (!in_array($nextRole, ['admin', 'instruktur', 'peserta'], true)) return elips_fail('Role pengguna tidak valid.');
    if (!in_array($nextStatus, ['aktif', 'nonaktif'], true)) return elips_fail('Status pengguna tidak valid.');
    if ($password !== '' && strlen($password) < 8) return elips_fail('Password minimal 8 karakter.');

    $stmt = elips_db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$nextEmail, $id]);
    if ($stmt->fetch()) return elips_fail('Email sudah digunakan oleh pengguna lain.');

    $previous = [
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status'],
        'phone' => $user['phone'],
    ];

    if ($password !== '') {
        $stmt = elips_db()->prepare(
            'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ?, password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $stmt->execute([$nextName, $nextEmail, $nextPhone, $nextRole, $nextStatus, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
        $stmt = elips_db()->prepare(
            'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $stmt->execute([$nextName, $nextEmail, $nextPhone, $nextRole, $nextStatus, $id]);
    }

    if ($previous['role'] === 'instruktur' && $nextRole !== 'instruktur') {
        elips_release_instructor_courses($id, false);
    }
    if ($nextRole === 'instruktur' && $nextStatus !== 'aktif') {
        elips_release_instructor_courses($id, true);
    }

    $changes = [];
    if ($previous['name'] !== $nextName) $changes[] = 'nama';
    if ($previous['email'] !== $nextEmail) $changes[] = 'email';
    if ($previous['role'] !== $nextRole) $changes[] = 'role';
    if ($previous['status'] !== $nextStatus) $changes[] = 'status';
    if (array_key_exists('phone', $data) && $previous['phone'] !== $nextPhone) $changes[] = 'telepon';
    if ($password !== '') $changes[] = 'password';

    if ($changes) {
        elips_audit(
            'update',
            'user',
            $id,
            $nextName,
            'Memperbarui pengguna "' . $nextName . '" (' . implode(', ', $changes) . ').',
            $actor
        );
    }

    return elips_ok('Pengguna berhasil diperbarui.', elips_public_user($id));
}

function elips_action_user_delete(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $user = elips_user_row($id);
    if (!$user) return elips_fail('Pengguna tidak ditemukan.');
    if ($id === 1) return elips_fail('Akun Administrator utama tidak dapat dihapus.');

    if ($user['role'] === 'admin') {
        $count = (int) elips_db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($count <= 1) return elips_fail('Minimal satu akun Admin harus tersedia.');
    }

    if ($user['role'] === 'instruktur') {
        elips_release_instructor_courses($id, false);
    }

    $stmt = elips_db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'user',
        $id,
        $user['name'],
        'Menghapus pengguna "' . $user['name'] . '" dengan role ' . $user['role'] . '.',
        $actor
    );

    return elips_ok('Pengguna dihapus.');
}

function elips_action_user_assign_courses(array $p): array
{
    $actor = elips_require_admin();
    $instructorId = (int) ($p['instructorId'] ?? 0);
    $instructor = elips_user_row($instructorId);

    if (!$instructor || $instructor['role'] !== 'instruktur') {
        return elips_fail('Pilih akun instruktur yang valid.');
    }
    if ($instructor['status'] !== 'aktif') {
        return elips_fail('Instruktur harus berstatus aktif.');
    }

    $selected = [];
    foreach (elips_payload_array($p, 'courseIds') as $courseId) {
        $courseId = (int) $courseId;
        if ($courseId > 0) $selected[$courseId] = true;
    }

    $db = elips_db();
    $courses = $db->query('SELECT * FROM courses')->fetchAll();
    $changed = 0;

    foreach ($courses as $course) {
        $courseId = (int) $course['id'];
        $current = $course['instructor_id'] === null ? null : (int) $course['instructor_id'];

        if (isset($selected[$courseId])) {
            if ($current !== $instructorId) {
                $stmt = $db->prepare('UPDATE courses SET instructor_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
                $stmt->execute([$instructorId, $courseId]);
                $changed++;
            }
        } elseif ($current === $instructorId) {
            $stmt = $db->prepare("UPDATE courses SET instructor_id = NULL, status = 'draft', updated_at = UTC_TIMESTAMP() WHERE id = ?");
            $stmt->execute([$courseId]);
            $changed++;
        }
    }

    if ($changed > 0) {
        elips_sync_content_owners();
        elips_audit(
            'assign',
            'course',
            null,
            $instructor['name'],
            'Memperbarui ' . $changed . ' penugasan kursus untuk instruktur "' . $instructor['name'] . '".',
            $actor
        );
    }

    return elips_ok($changed . ' penugasan kursus diperbarui.', ['changed' => $changed]);
}

// ---------- Courses ----------

function elips_action_course_create(array $p): array
{
    $actor = elips_require_admin();
    $title = trim((string) ($p['title'] ?? ''));
    $category = trim((string) ($p['category'] ?? ''));
    $description = trim((string) ($p['description'] ?? ''));
    $instructorId = (int) ($p['instructorId'] ?? 0);

    if ($title === '') return elips_fail('Judul kursus wajib diisi.');
    if ($category === '') return elips_fail('Kategori kursus wajib diisi.');
    if (!$instructorId) return elips_fail('Pilih satu instruktur untuk kursus ini.');
    if (!elips_is_active_instructor($instructorId)) return elips_fail('Instruktur harus berstatus aktif.');

    $instructor = elips_user_row($instructorId);
    $stmt = elips_db()->prepare(
        "INSERT INTO courses (title, category, description, instructor_id, status, created_at)
         VALUES (?, ?, ?, ?, 'draft', UTC_TIMESTAMP())"
    );
    $stmt->execute([$title, $category, $description, $instructorId]);
    $id = (int) elips_db()->lastInsertId();

    elips_audit(
        'create',
        'course',
        $id,
        $title,
        'Membuat kursus "' . $title . '" (Draft) dengan instruktur ' . $instructor['name'] . '.',
        $actor
    );

    return elips_ok('Kursus baru berhasil ditambahkan dan berstatus Draft.', elips_course_public($id));
}

function elips_action_course_update(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $data = elips_payload_array($p, 'data');

    $course = elips_course_row($id);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');

    $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $course['title'];
    $category = array_key_exists('category', $data) ? trim((string) $data['category']) : $course['category'];
    $description = array_key_exists('description', $data) ? trim((string) $data['description']) : $course['description'];
    $nextInstructorId = array_key_exists('instructorId', $data) ? (int) $data['instructorId'] : (int) ($course['instructor_id'] ?? 0);

    if ($title === '') return elips_fail('Judul kursus wajib diisi.');
    if ($category === '') return elips_fail('Kategori kursus wajib diisi.');
    if (!$nextInstructorId) return elips_fail('Pilih satu instruktur untuk kursus ini.');
    if (!elips_is_active_instructor($nextInstructorId)) return elips_fail('Instruktur harus berstatus aktif.');

    $previousInstructorId = $course['instructor_id'] === null ? null : (int) $course['instructor_id'];
    $instructor = elips_user_row($nextInstructorId);

    $changes = [];
    if ($course['title'] !== $title) $changes[] = 'judul';
    if ($course['category'] !== $category) $changes[] = 'kategori';
    if ((string) $course['description'] !== $description) $changes[] = 'deskripsi';
    if ($previousInstructorId !== $nextInstructorId) $changes[] = 'instruktur';

    $stmt = elips_db()->prepare(
        'UPDATE courses SET title = ?, category = ?, description = ?, instructor_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([$title, $category, $description, $nextInstructorId, $id]);

    if ($previousInstructorId !== $nextInstructorId) {
        elips_sync_content_owners();
    }

    if ($changes) {
        elips_audit(
            'update',
            'course',
            $id,
            $title,
            'Memperbarui kursus "' . $title . '" (' . implode(', ', $changes) . ').',
            $actor
        );
    }

    return elips_ok('Kursus berhasil diperbarui.', elips_course_public($id));
}

function elips_action_course_delete(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $course = elips_course_row($id);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');

    $stmt = elips_db()->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'course',
        $id,
        $course['title'],
        'Menghapus kursus "' . $course['title'] . '" beserta modul, materi, penugasan, nilai, sertifikat, dan progres terkait.',
        $actor
    );

    return elips_ok('Kursus beserta seluruh datanya berhasil dihapus.');
}

function elips_action_course_status(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $status = (string) ($p['status'] ?? '');

    if (!in_array($status, ['draft', 'published'], true)) {
        return elips_fail('Status kursus tidak valid.');
    }

    $course = elips_course_row($id);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    $previousStatus = $course['status'];

    if ($status === 'published') {
        if (!elips_is_active_instructor($course['instructor_id'] === null ? null : (int) $course['instructor_id'])) {
            return elips_fail('Tugaskan instruktur aktif sebelum mempublikasikan kursus.');
        }
        $stmt = elips_db()->prepare('SELECT COUNT(*) FROM modules WHERE course_id = ?');
        $stmt->execute([$id]);
        $moduleCount = (int) $stmt->fetchColumn();
        $stmt = elips_db()->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ?');
        $stmt->execute([$id]);
        $lessonCount = (int) $stmt->fetchColumn();
        if ($moduleCount === 0 || $lessonCount === 0) {
            return elips_fail('Tambahkan minimal satu modul dan satu materi sebelum publikasi.');
        }
    }

    $stmt = elips_db()->prepare('UPDATE courses SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$status, $id]);

    if ($previousStatus !== $status) {
        elips_audit(
            $status === 'published' ? 'publish' : 'unpublish',
            'course',
            $id,
            $course['title'],
            $status === 'published'
                ? 'Mempublikasikan kursus "' . $course['title'] . '".'
                : 'Mengembalikan kursus "' . $course['title'] . '" ke Draft.',
            $actor
        );
        if ($status === 'published') {
            elips_notify_course_published($id);
        }
    }

    return elips_ok(
        $status === 'published' ? 'Kursus berhasil dipublikasikan.' : 'Kursus dikembalikan ke Draft.',
        elips_course_public($id)
    );
}

// ---------- Enrollments ----------

function elips_action_enrollment_assign(array $p): array
{
    $actor = elips_require_admin();
    $studentId = (int) ($p['studentId'] ?? 0);
    $student = elips_user_row($studentId);

    if (!$student || $student['role'] !== 'peserta') return elips_fail('Pilih akun peserta yang valid.');
    if ($student['status'] !== 'aktif') return elips_fail('Peserta harus berstatus aktif.');

    $requested = [];
    foreach (elips_payload_array($p, 'courseIds') as $courseId) {
        $courseId = (int) $courseId;
        if ($courseId > 0) $requested[$courseId] = true;
    }
    if (!$requested) return elips_fail('Pilih minimal satu kursus.');

    $db = elips_db();
    foreach (array_keys($requested) as $courseId) {
        if (!elips_course_row($courseId)) return elips_fail('Ada kursus yang tidak ditemukan.');
    }

    $enrolledAt = trim((string) ($p['enrolledAt'] ?? ''));
    if ($enrolledAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $enrolledAt)) {
        $enrolledAt = gmdate('Y-m-d');
    }

    $added = 0;
    foreach (array_keys($requested) as $courseId) {
        $stmt = $db->prepare('SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1');
        $stmt->execute([$studentId, $courseId]);
        if ($stmt->fetch()) continue;

        $stmt = $db->prepare(
            "INSERT INTO enrollments (student_id, course_id, enrolled_by, enrolled_at, status, created_at)
             VALUES (?, ?, 'admin', ?, 'active', UTC_TIMESTAMP())"
        );
        $stmt->execute([$studentId, $courseId, $enrolledAt]);
        $enrollmentId = (int) $db->lastInsertId();
        $added++;

        $course = elips_course_row($courseId);
        elips_audit(
            'create',
            'enrollment',
            $enrollmentId,
            $student['name'] . ' — ' . ($course ? $course['title'] : 'Kursus #' . $courseId),
            'Menugaskan "' . $student['name'] . '" ke kursus "' . ($course ? $course['title'] : 'Kursus #' . $courseId) . '".',
            $actor
        );
    }

    return elips_ok(
        $added > 0 ? $added . ' penugasan berhasil dibuat.' : 'Semua kursus yang dipilih sudah ditugaskan.',
        ['added' => $added]
    );
}

function elips_action_enrollment_status(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $status = (string) ($p['status'] ?? '');

    if (!in_array($status, ['active', 'dihentikan'], true)) {
        return elips_fail('Status penugasan tidak valid.');
    }
    $enrollment = elips_enrollment_row($id);
    if (!$enrollment) return elips_fail('Penugasan tidak ditemukan.');

    $stmt = elips_db()->prepare('UPDATE enrollments SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$status, $id]);

    $student = elips_user_row((int) $enrollment['student_id']);
    $course = elips_course_row((int) $enrollment['course_id']);
    $label = ($student ? $student['name'] : 'Peserta #' . $enrollment['student_id']) . ' — ' .
        ($course ? $course['title'] : 'Kursus #' . $enrollment['course_id']);

    elips_audit(
        'status',
        'enrollment',
        $id,
        $label,
        ($status === 'active' ? 'Mengaktifkan' : 'Menghentikan') . ' penugasan "' . $label . '".',
        $actor
    );

    return elips_ok(
        $status === 'active' ? 'Penugasan diaktifkan.' : 'Penugasan dihentikan.',
        elips_enrollment_public($id)
    );
}

function elips_action_enrollment_delete(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $enrollment = elips_enrollment_row($id);
    if (!$enrollment) return elips_fail('Penugasan tidak ditemukan.');

    $studentId = (int) $enrollment['student_id'];
    $courseId = (int) $enrollment['course_id'];
    $student = elips_user_row($studentId);
    $course = elips_course_row($courseId);
    $label = ($student ? $student['name'] : 'Peserta #' . $studentId) . ' — ' .
        ($course ? $course['title'] : 'Kursus #' . $courseId);

    $db = elips_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('DELETE FROM grades WHERE student_id = ? AND course_id = ?');
        $stmt->execute([$studentId, $courseId]);
        $stmt = $db->prepare('DELETE FROM certificates WHERE student_id = ? AND course_id = ?');
        $stmt->execute([$studentId, $courseId]);
        $stmt = $db->prepare('DELETE FROM completed_lessons WHERE student_id = ? AND course_id = ?');
        $stmt->execute([$studentId, $courseId]);
        $stmt = $db->prepare('DELETE FROM enrollments WHERE id = ?');
        $stmt->execute([$id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    elips_audit(
        'delete',
        'enrollment',
        $id,
        $label,
        'Menghapus penugasan "' . $label . '" beserta nilai, sertifikat, dan progres terkait.',
        $actor
    );

    return elips_ok('Penugasan dan data terkait berhasil dihapus.');
}

// ---------- Modules ----------

function elips_action_module_create(array $p): array
{
    $actor = elips_require_login();
    $courseId = (int) ($p['courseId'] ?? 0);
    $data = elips_payload_array($p, 'data');

    $course = elips_course_row($courseId);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak mengelola kursus ini.');

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') return elips_fail('Judul modul wajib diisi.');

    $db = elips_db();
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM modules WHERE course_id = ?');
    $stmt->execute([$courseId]);
    $nextOrder = (int) $stmt->fetchColumn() + 1;

    $stmt = $db->prepare(
        'INSERT INTO modules (course_id, instructor_id, title, description, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $courseId,
        $course['instructor_id'],
        $title,
        trim((string) ($data['description'] ?? '')),
        $nextOrder,
    ]);
    $id = (int) $db->lastInsertId();

    elips_audit(
        'create',
        'module',
        $id,
        $title,
        'Menambahkan modul "' . $title . '" pada kursus "' . $course['title'] . '".',
        $actor
    );

    return elips_ok('Modul berhasil ditambahkan.', elips_module_public($id));
}

function elips_action_module_update(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $data = elips_payload_array($p, 'data');

    $stmt = elips_db()->prepare('SELECT * FROM modules WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $module = $stmt->fetch();
    if (!$module) return elips_fail('Modul tidak ditemukan.');

    $course = elips_course_row((int) $module['course_id']);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak mengelola modul ini.');

    $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $module['title'];
    if ($title === '') return elips_fail('Judul modul wajib diisi.');
    $description = array_key_exists('description', $data) ? trim((string) $data['description']) : $module['description'];

    $stmt = elips_db()->prepare('UPDATE modules SET title = ?, description = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$title, $description, $id]);

    elips_audit(
        'update',
        'module',
        $id,
        $title,
        'Memperbarui modul "' . $title . '" pada kursus "' . $course['title'] . '".',
        $actor
    );

    return elips_ok('Modul berhasil diperbarui.', elips_module_public($id));
}

function elips_action_module_delete(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);

    $stmt = elips_db()->prepare('SELECT * FROM modules WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $module = $stmt->fetch();
    if (!$module) return elips_fail('Modul tidak ditemukan.');

    $course = elips_course_row((int) $module['course_id']);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak menghapus modul ini.');

    $stmt = elips_db()->prepare('SELECT COUNT(*) FROM lessons WHERE module_id = ?');
    $stmt->execute([$id]);
    $lessonCount = (int) $stmt->fetchColumn();

    $stmt = elips_db()->prepare('DELETE FROM modules WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'module',
        $id,
        $module['title'],
        'Menghapus modul "' . $module['title'] . '" dari kursus "' . $course['title'] . '" beserta ' . $lessonCount . ' materi.',
        $actor
    );

    return elips_ok('Modul dihapus.', ['removedLessons' => $lessonCount]);
}

// ---------- Lessons ----------

function elips_action_lesson_create(array $p): array
{
    $actor = elips_require_login();
    $moduleId = (int) ($p['moduleId'] ?? 0);
    $data = elips_payload_array($p, 'data');

    $stmt = elips_db()->prepare('SELECT * FROM modules WHERE id = ? LIMIT 1');
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch();
    if (!$module) return elips_fail('Modul tidak ditemukan.');

    $course = elips_course_row((int) $module['course_id']);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak mengelola materi ini.');

    $title = trim((string) ($data['title'] ?? ''));
    $type = (string) ($data['type'] ?? 'text');
    $content = trim((string) ($data['content'] ?? ''));

    if ($title === '') return elips_fail('Judul materi wajib diisi.');
    if (!in_array($type, ['text', 'video', 'file', 'link'], true)) return elips_fail('Tipe konten tidak valid.');
    if ($type !== 'text' && $content === '') return elips_fail('Isi URL atau nama file untuk tipe konten ini.');
    if ($type === 'text') $content = elips_sanitize_html($content);

    $db = elips_db();
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM lessons WHERE module_id = ?');
    $stmt->execute([$moduleId]);
    $nextOrder = (int) $stmt->fetchColumn() + 1;

    $stmt = $db->prepare(
        'INSERT INTO lessons (module_id, course_id, instructor_id, title, type, content, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $moduleId,
        (int) $module['course_id'],
        $module['instructor_id'],
        $title,
        $type,
        $content,
        $nextOrder,
    ]);
    $id = (int) $db->lastInsertId();

    elips_audit(
        'create',
        'lesson',
        $id,
        $title,
        'Menambahkan materi "' . $title . '" pada modul "' . $module['title'] . '" kursus "' . $course['title'] . '".',
        $actor
    );

    return elips_ok('Materi berhasil ditambahkan.', elips_lesson_public($id));
}

function elips_action_lesson_update(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $data = elips_payload_array($p, 'data');

    $stmt = elips_db()->prepare('SELECT * FROM lessons WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $lesson = $stmt->fetch();
    if (!$lesson) return elips_fail('Materi tidak ditemukan.');

    $course = elips_course_row((int) $lesson['course_id']);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak mengelola materi ini.');

    $targetModuleId = array_key_exists('moduleId', $data) ? (int) $data['moduleId'] : (int) $lesson['module_id'];
    $stmt = elips_db()->prepare('SELECT * FROM modules WHERE id = ? LIMIT 1');
    $stmt->execute([$targetModuleId]);
    $targetModule = $stmt->fetch();
    if (!$targetModule || (int) $targetModule['course_id'] !== (int) $lesson['course_id']) {
        return elips_fail('Modul tujuan tidak valid untuk kursus ini.');
    }

    $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $lesson['title'];
    $type = array_key_exists('type', $data) ? (string) $data['type'] : $lesson['type'];
    $content = array_key_exists('content', $data) ? trim((string) $data['content']) : $lesson['content'];

    if ($title === '') return elips_fail('Judul materi wajib diisi.');
    if (!in_array($type, ['text', 'video', 'file', 'link'], true)) return elips_fail('Tipe konten tidak valid.');
    if ($type !== 'text' && $content === '') return elips_fail('Isi URL atau nama file untuk tipe konten ini.');
    if ($type === 'text') $content = elips_sanitize_html($content);

    $db = elips_db();
    $sortOrder = (int) $lesson['sort_order'];
    if ((int) $lesson['module_id'] !== $targetModuleId) {
        $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM lessons WHERE module_id = ?');
        $stmt->execute([$targetModuleId]);
        $sortOrder = (int) $stmt->fetchColumn() + 1;
    }

    $stmt = $db->prepare(
        'UPDATE lessons SET module_id = ?, instructor_id = ?, title = ?, type = ?, content = ?, sort_order = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([
        $targetModuleId,
        $targetModule['instructor_id'],
        $title,
        $type,
        $content,
        $sortOrder,
        $id,
    ]);

    elips_audit(
        'update',
        'lesson',
        $id,
        $title,
        'Memperbarui materi "' . $title . '" pada modul "' . $targetModule['title'] . '" kursus "' . $course['title'] . '".',
        $actor
    );

    return elips_ok('Materi berhasil diperbarui.', elips_lesson_public($id));
}

function elips_action_lesson_delete(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);

    $stmt = elips_db()->prepare('SELECT * FROM lessons WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $lesson = $stmt->fetch();
    if (!$lesson) return elips_fail('Materi tidak ditemukan.');

    $course = elips_course_row((int) $lesson['course_id']);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak menghapus materi ini.');

    $stmt = elips_db()->prepare('DELETE FROM lessons WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'lesson',
        $id,
        $lesson['title'],
        'Menghapus materi "' . $lesson['title'] . '" dari kursus "' . $course['title'] . '".',
        $actor
    );

    return elips_ok('Materi dihapus.', elips_lesson_public($id) ?: ['id' => $id]);
}

// ---------- Grades & certificates ----------

function elips_action_grade_save(array $p): array
{
    $actor = elips_require_login();
    $studentId = (int) ($p['studentId'] ?? 0);
    $courseId = (int) ($p['courseId'] ?? 0);
    $score = (int) ($p['score'] ?? -1);
    $note = trim((string) ($p['note'] ?? ''));

    if (!$studentId || !$courseId) return elips_fail('Peserta atau kursus tidak valid.');
    if ($score < 0 || $score > 100) return elips_fail('Masukkan nilai antara 0-100.');

    $course = elips_course_row($courseId);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak menilai kursus ini.');

    $db = elips_db();
    $enrollment = null;
    if (!empty($p['enrollmentId'])) {
        $enrollment = elips_enrollment_row((int) $p['enrollmentId']);
    }
    if (!$enrollment) {
        $stmt = $db->prepare('SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1');
        $stmt->execute([$studentId, $courseId]);
        $enrollment = $stmt->fetch() ?: null;
    }
    if (!$enrollment || (int) $enrollment['student_id'] !== $studentId || (int) $enrollment['course_id'] !== $courseId) {
        return elips_fail('Penugasan peserta pada kursus ini tidak ditemukan.');
    }

    $letter = elips_grade_letter($score);
    $enrollmentId = (int) $enrollment['id'];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM grades WHERE enrollment_id = ? OR (student_id = ? AND course_id = ?) LIMIT 1');
        $stmt->execute([$enrollmentId, $studentId, $courseId]);
        $grade = $stmt->fetch() ?: null;
        $isNewGrade = $grade === null;

        if ($grade) {
            $stmt = $db->prepare(
                'UPDATE grades SET enrollment_id = ?, score = ?, grade = ?, note = ?, instructor_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
            );
            $stmt->execute([$enrollmentId, $score, $letter, $note, (int) $actor['id'], (int) $grade['id']]);
            $gradeId = (int) $grade['id'];
        } else {
            $stmt = $db->prepare(
                'INSERT INTO grades (enrollment_id, course_id, student_id, instructor_id, score, grade, note, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            );
            $stmt->execute([$enrollmentId, $courseId, $studentId, (int) $actor['id'], $score, $letter, $note]);
            $gradeId = (int) $db->lastInsertId();
        }

        $stmt = $db->prepare('SELECT * FROM certificates WHERE enrollment_id = ? OR (student_id = ? AND course_id = ?) LIMIT 1');
        $stmt->execute([$enrollmentId, $studentId, $courseId]);
        $certificate = $stmt->fetch() ?: null;
        $certificateExisted = $certificate !== null;

        if ($certificate) {
            $certificateId = (int) $certificate['id'];
            $number = $certificate['certificate_number'] ?: elips_unique_certificate_number('CERT-DRAFT-' . substr((string) time(), -6) . '-');
            $stmt = $db->prepare(
                "UPDATE certificates
                 SET enrollment_id = ?, grade = ?, score = ?, issued_by_instructor_at = UTC_TIMESTAMP(),
                     status = IF(status = 'publish', 'publish', 'draft'), certificate_number = ?, updated_at = UTC_TIMESTAMP()
                 WHERE id = ?"
            );
            $stmt->execute([$enrollmentId, $letter, $score, $number, $certificateId]);
        } else {
            $number = elips_unique_certificate_number('CERT-DRAFT-' . substr((string) time(), -6) . '-');
            $stmt = $db->prepare(
                "INSERT INTO certificates
                 (student_id, course_id, enrollment_id, certificate_number, status, grade, score,
                  issued_by_instructor_at, created_at)
                 VALUES (?, ?, ?, ?, 'draft', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            $stmt->execute([$studentId, $courseId, $enrollmentId, $number, $letter, $score]);
            $certificateId = (int) $db->lastInsertId();
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $student = elips_user_row($studentId);
    $studentName = $student ? $student['name'] : 'Peserta #' . $studentId;

    elips_audit(
        'grade',
        'grade',
        $gradeId,
        $studentName . ' — ' . $course['title'],
        ($isNewGrade ? 'Memberi' : 'Memperbarui') . ' nilai ' . $score . ' (' . $letter . ')'
            . ($note ? ' — catatan: ' . $note : '') . ' untuk ' . $studentName
            . ' pada kursus "' . $course['title'] . '".',
        $actor
    );

    if (!$certificateExisted) {
        $certificatePublic = elips_certificate_public($certificateId);
        elips_audit(
            'submit',
            'certificate',
            $certificateId,
            $certificatePublic['certificateNumber'] ?? '',
            'Membuat e-sertifikat Draft ' . ($certificatePublic['certificateNumber'] ?? '') . ' untuk '
                . $studentName . ' pada kursus "' . $course['title'] . '".',
            $actor
        );
    }

    return elips_ok('Nilai disimpan. E-sertifikat Draft menunggu persetujuan Admin.', [
        'grade' => elips_grade_public($gradeId),
        'certificate' => elips_certificate_public($certificateId),
    ]);
}

function elips_action_certificate_draft(array $p): array
{
    $actor = elips_require_login();
    $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
    $enrollment = elips_enrollment_row($enrollmentId);
    if (!$enrollment) return elips_fail('Penugasan peserta tidak ditemukan.');

    $course = elips_course_row((int) $enrollment['course_id']);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if (!elips_can_manage_course($course, $actor)) return elips_fail('Anda tidak berhak mengajukan sertifikat kursus ini.');

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM certificates WHERE student_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([(int) $enrollment['student_id'], (int) $enrollment['course_id']]);
    if ($stmt->fetch()) return elips_fail('Sertifikat peserta ini sudah ada.');

    $stmt = $db->prepare('SELECT * FROM grades WHERE student_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([(int) $enrollment['student_id'], (int) $enrollment['course_id']]);
    $grade = $stmt->fetch();
    if (!$grade) return elips_fail('Peserta belum memiliki nilai.');

    $number = elips_unique_certificate_number('CERT-DRAFT-' . substr((string) time(), -6) . '-');
    $stmt = $db->prepare(
        "INSERT INTO certificates
         (student_id, course_id, enrollment_id, certificate_number, status, grade, score,
          issued_by_instructor_at, created_at)
         VALUES (?, ?, ?, ?, 'draft', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
    );
    $stmt->execute([
        (int) $enrollment['student_id'],
        (int) $enrollment['course_id'],
        $enrollmentId,
        $number,
        $grade['grade'],
        (int) $grade['score'],
    ]);
    $id = (int) $db->lastInsertId();

    $student = elips_user_row((int) $enrollment['student_id']);
    elips_audit(
        'submit',
        'certificate',
        $id,
        $number,
        'Mengajukan e-sertifikat Draft ' . $number . ' untuk '
            . ($student ? $student['name'] : 'Peserta #' . $enrollment['student_id'])
            . ' pada kursus "' . $course['title'] . '".',
        $actor
    );

    return elips_ok('E-sertifikat Draft berhasil diajukan ke Admin.', elips_certificate_public($id));
}

function elips_action_certificate_approve(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);

    $stmt = elips_db()->prepare('SELECT * FROM certificates WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $certificate = $stmt->fetch();
    if (!$certificate) return elips_fail('Sertifikat tidak ditemukan.');
    if ($certificate['status'] !== 'draft') return elips_fail('Sertifikat ini sudah berstatus Publish.');

    $previousNumber = $certificate['certificate_number'];
    $newNumber = elips_generate_certificate_number();

    $stmt = elips_db()->prepare(
        "UPDATE certificates
         SET status = 'publish', approved_by_admin_id = ?, approved_at = UTC_TIMESTAMP(),
             certificate_number = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?"
    );
    $stmt->execute([(int) $actor['id'], $newNumber, $id]);

    $student = elips_user_row((int) $certificate['student_id']);
    $course = elips_course_row((int) $certificate['course_id']);

    elips_audit(
        'approve',
        'certificate',
        $id,
        $newNumber,
        'Menyetujui e-sertifikat Draft ' . $previousNumber . ' menjadi Publish (' . $newNumber . ') untuk '
            . ($student ? $student['name'] : 'Peserta #' . $certificate['student_id'])
            . ' pada kursus "' . ($course ? $course['title'] : 'Kursus #' . $certificate['course_id']) . '".',
        $actor
    );

    elips_notify_certificate_published($id);

    return elips_ok('Sertifikat berhasil disetujui dan diterbitkan.', elips_certificate_public($id));
}

// ---------- Progress ----------

function elips_action_progress_complete(array $p): array
{
    $studentId = (int) ($p['studentId'] ?? 0);
    $courseId = (int) ($p['courseId'] ?? 0);
    $lessonId = (int) ($p['lessonId'] ?? 0);

    $actor = elips_session_user();
    if (!$actor) return elips_fail('Anda harus masuk terlebih dahulu.');
    if ($actor['role'] !== 'peserta' || (int) $actor['id'] !== $studentId) {
        return elips_fail('Anda tidak berhak menandai materi ini.');
    }

    $course = elips_course_row($courseId);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if ($course['status'] !== 'published') return elips_fail('Kursus belum dipublikasikan.');

    $db = elips_db();
    $stmt = $db->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$studentId, $courseId]);
    if (!$stmt->fetch()) return elips_fail('Anda tidak terdaftar pada kursus ini.');

    $stmt = $db->prepare('SELECT * FROM lessons WHERE id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$lessonId, $courseId]);
    $lesson = $stmt->fetch();
    if (!$lesson) return elips_fail('Materi tidak ditemukan pada kursus ini.');

    // Urutan materi mengikuti urutan modul lalu urutan materi.
    $stmt = $db->prepare(
        'SELECT l.id FROM lessons l JOIN modules m ON m.id = l.module_id
         WHERE l.course_id = ? ORDER BY m.sort_order ASC, m.id ASC, l.sort_order ASC, l.id ASC'
    );
    $stmt->execute([$courseId]);
    $ordered = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $index = array_search($lessonId, array_map('intval', $ordered), true);
    if ($index === false) return elips_fail('Materi tidak ditemukan pada kursus ini.');

    if ($index > 0) {
        $previousIds = array_slice(array_map('intval', $ordered), 0, $index);
        $placeholders = implode(',', array_fill(0, count($previousIds), '?'));
        $params = array_merge([$studentId], $previousIds);
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT lesson_id) FROM completed_lessons WHERE student_id = ? AND lesson_id IN (' . $placeholders . ')'
        );
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() < count($previousIds)) {
            return elips_fail('Selesaikan materi sebelumnya terlebih dahulu.');
        }
    }

    $stmt = $db->prepare(
        'INSERT IGNORE INTO completed_lessons (student_id, course_id, lesson_id, completed_at)
         VALUES (?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([$studentId, $courseId, $lessonId]);

    return elips_ok('Materi ditandai selesai.', [
        'studentId' => $studentId,
        'courseId' => $courseId,
        'lessonId' => $lessonId,
    ]);
}

// ---------- Admin housekeeping ----------

function elips_action_audit_clear(): array
{
    $actor = elips_require_admin();
    elips_db()->exec('DELETE FROM audit_logs');
    return elips_ok('Seluruh audit trail berhasil dibersihkan.');
}

function elips_action_notification_clear(): array
{
    $actor = elips_require_admin();
    elips_db()->exec('DELETE FROM notifications');
    return elips_ok('Seluruh outbox notifikasi berhasil dibersihkan.');
}
