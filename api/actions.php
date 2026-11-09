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
        case 'branch.create':
            return elips_action_branch_create($payload);
        case 'branch.update':
            return elips_action_branch_update($payload);
        case 'branch.status':
            return elips_action_branch_status($payload);
        case 'branch.delete':
            return elips_action_branch_delete($payload);
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
        case 'curriculum.create':
            return elips_action_curriculum_create($payload);
        case 'curriculum.update':
            return elips_action_curriculum_update($payload);
        case 'curriculum.publish':
            return elips_action_curriculum_publish($payload);
        case 'curriculum.clone':
            return elips_action_curriculum_clone($payload);
        case 'curriculum.retire':
            return elips_action_curriculum_retire($payload);
        case 'curriculum.delete':
            return elips_action_curriculum_delete($payload);
        case 'curriculum.detail':
            return elips_action_curriculum_detail($payload);
        case 'class.create':
            return elips_action_class_create($payload);
        case 'class.update':
            return elips_action_class_update($payload);
        case 'class.status':
            return elips_action_class_status($payload);
        case 'class.assignInstructor':
            return elips_action_class_assign_instructor($payload);
        case 'class.delete':
            return elips_action_class_delete($payload);
        case 'enrollment.assign':
            return elips_action_enrollment_assign($payload);
        case 'enrollment.assignClass':
            return elips_action_enrollment_assign_class($payload);
        case 'enrollment.status':
            return elips_action_enrollment_status($payload);
        case 'enrollment.classStatus':
            return elips_action_enrollment_class_status($payload);
        case 'enrollment.delete':
            return elips_action_enrollment_delete($payload);
        case 'session.create':
            return elips_action_session_create($payload);
        case 'session.createBulk':
            return elips_action_session_create_bulk($payload);
        case 'session.update':
            return elips_action_session_update($payload);
        case 'session.status':
            return elips_action_session_status($payload);
        case 'session.delete':
            return elips_action_session_delete($payload);
        case 'session.detail':
            return elips_action_session_detail($payload);
        case 'attendance.saveBulk':
            return elips_action_attendance_save_bulk($payload);
        case 'attendance.correct':
            return elips_action_attendance_correct($payload);
        case 'attendance.recap':
            return elips_action_attendance_recap($payload);
        case 'attendance.myRecap':
            return elips_action_attendance_my_recap($payload);
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
        case 'material.settings':
            return elips_action_material_settings($payload);
        case 'material.updateSettings':
            return elips_action_material_update_settings($payload);
        case 'material.create':
            return elips_action_material_create($payload);
        case 'material.update':
            return elips_action_material_update($payload);
        case 'material.status':
            return elips_action_material_status($payload);
        case 'material.delete':
            return elips_action_material_delete($payload);
        case 'material.classroom':
            return elips_action_material_classroom($payload);
        case 'material.progress':
            return elips_action_material_progress($payload);
        case 'assignment.classroom':
            return elips_action_assignment_classroom($payload);
        case 'assignment.detail':
            return elips_action_assignment_detail($payload);
        case 'assignment.create':
            return elips_action_assignment_create($payload);
        case 'assignment.update':
            return elips_action_assignment_update($payload);
        case 'assignment.status':
            return elips_action_assignment_status($payload);
        case 'assignment.delete':
            return elips_action_assignment_delete($payload);
        case 'assignment.submit':
            return elips_action_assignment_submit($payload);
        case 'assignment.review':
            return elips_action_assignment_review($payload);
        case 'assignment.submissions':
            return elips_action_assignment_submissions($payload);
        case 'exam.classroom':
            return elips_action_exam_classroom($payload);
        case 'exam.detail':
            return elips_action_exam_detail($payload);
        case 'exam.create':
            return elips_action_exam_create($payload);
        case 'exam.update':
            return elips_action_exam_update($payload);
        case 'exam.status':
            return elips_action_exam_status($payload);
        case 'exam.delete':
            return elips_action_exam_delete($payload);
        case 'exam.result':
            return elips_action_exam_result($payload);
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

// ---------- Branches ----------

function elips_generate_branch_code(string $name): string
{
    $clean = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? '');
    $clean = substr($clean, 0, 4);
    if ($clean === '') {
        $clean = 'CBG';
    }
    $db = elips_db();
    $base = 'CBG-' . $clean;
    $code = $base;
    $suffix = 2;
    $stmt = $db->prepare('SELECT id FROM branches WHERE code = ? LIMIT 1');
    while (true) {
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
        $code = $base . '-' . $suffix++;
    }
}

function elips_action_branch_create(array $p): array
{
    $actor = elips_require_admin();
    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') return elips_fail('Nama cabang wajib diisi.');

    $code = trim((string) ($p['code'] ?? ''));
    if ($code === '') $code = elips_generate_branch_code($name);

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM branches WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    if ($stmt->fetch()) return elips_fail('Kode cabang sudah dipakai.');

    $status = (string) ($p['status'] ?? 'active');
    if (!in_array($status, ['active', 'inactive'], true)) {
        return elips_fail('Status cabang tidak valid.');
    }

    $stmt = $db->prepare(
        'INSERT INTO branches (code, name, address, city, phone, manager, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $code,
        $name,
        trim((string) ($p['address'] ?? '')),
        trim((string) ($p['city'] ?? '')),
        trim((string) ($p['phone'] ?? '')),
        trim((string) ($p['manager'] ?? '')),
        $status,
        trim((string) ($p['notes'] ?? '')),
    ]);
    $id = (int) $db->lastInsertId();

    elips_audit(
        'create',
        'branch',
        $id,
        $name,
        'Membuat cabang "' . $name . '" (' . $code . ') di ' . (trim((string) ($p['city'] ?? '')) ?: 'lokasi belum diisi') . '.',
        $actor
    );

    return elips_ok('Cabang berhasil dibuat.', elips_branch_public($id));
}

function elips_action_branch_update(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $data = elips_payload_array($p, 'data');
    $branch = elips_branch_row($id);
    if (!$branch) return elips_fail('Cabang tidak ditemukan.');

    $name = array_key_exists('name', $data) ? trim((string) $data['name']) : $branch['name'];
    if ($name === '') return elips_fail('Nama cabang wajib diisi.');

    $code = array_key_exists('code', $data) ? trim((string) $data['code']) : $branch['code'];
    if ($code === '') return elips_fail('Kode cabang wajib diisi.');

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM branches WHERE code = ? AND id <> ? LIMIT 1');
    $stmt->execute([$code, $id]);
    if ($stmt->fetch()) return elips_fail('Kode cabang sudah dipakai.');

    $status = array_key_exists('status', $data) ? (string) $data['status'] : $branch['status'];
    if (!in_array($status, ['active', 'inactive'], true)) {
        return elips_fail('Status cabang tidak valid.');
    }

    $stmt = $db->prepare(
        'UPDATE branches
         SET code = ?, name = ?, address = ?, city = ?, phone = ?, manager = ?, status = ?, notes = ?,
             updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([
        $code,
        $name,
        array_key_exists('address', $data) ? trim((string) $data['address']) : $branch['address'],
        array_key_exists('city', $data) ? trim((string) $data['city']) : $branch['city'],
        array_key_exists('phone', $data) ? trim((string) $data['phone']) : $branch['phone'],
        array_key_exists('manager', $data) ? trim((string) $data['manager']) : $branch['manager'],
        $status,
        array_key_exists('notes', $data) ? trim((string) $data['notes']) : $branch['notes'],
        $id,
    ]);

    elips_audit(
        'update',
        'branch',
        $id,
        $name,
        'Memperbarui cabang "' . $name . '" (' . $code . ').',
        $actor
    );

    return elips_ok('Cabang berhasil diperbarui.', elips_branch_public($id));
}

function elips_action_branch_status(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $status = (string) ($p['status'] ?? '');
    if (!in_array($status, ['active', 'inactive'], true)) {
        return elips_fail('Status cabang tidak valid.');
    }

    $branch = elips_branch_row($id);
    if (!$branch) return elips_fail('Cabang tidak ditemukan.');

    $stmt = elips_db()->prepare('UPDATE branches SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$status, $id]);

    elips_audit(
        'status',
        'branch',
        $id,
        $branch['name'],
        'Mengubah status cabang "' . $branch['name'] . '" dari ' . $branch['status'] . ' menjadi ' . $status . '.',
        $actor
    );

    return elips_ok('Status cabang diperbarui menjadi ' . ($status === 'active' ? 'aktif' : 'nonaktif') . '.', elips_branch_public($id));
}

function elips_action_branch_delete(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $branch = elips_branch_row($id);
    if (!$branch) return elips_fail('Cabang tidak ditemukan.');

    $stmt = elips_db()->prepare('SELECT COUNT(*) FROM classes WHERE branch_id = ?');
    $stmt->execute([$id]);
    $classCount = (int) $stmt->fetchColumn();
    if ($classCount > 0) {
        return elips_fail('Cabang tidak dapat dihapus karena masih dipakai oleh ' . $classCount . ' kelas.');
    }

    $stmt = elips_db()->prepare('DELETE FROM branches WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'branch',
        $id,
        $branch['name'],
        'Menghapus cabang "' . $branch['name'] . '" (' . $branch['code'] . ').',
        $actor
    );

    return elips_ok('Cabang berhasil dihapus.');
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
    $categoryId = elips_resolve_category_id(isset($p['categoryId']) ? (int) $p['categoryId'] : null, $category);
    $stmt = elips_db()->prepare(
        "INSERT INTO courses (title, category, category_id, description, instructor_id, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'draft', UTC_TIMESTAMP())"
    );
    $stmt->execute([$title, $category, $categoryId, $description, $instructorId]);
    $id = (int) elips_db()->lastInsertId();

    // Setiap kursus baru dimulai dengan versi kurikulum 1.0 (draft) agar
    // modul dan materi selalu terhubung ke versi yang eksplisit.
    elips_ensure_curriculum_version($id, (int) $actor['id']);

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
    $categoryId = array_key_exists('categoryId', $data)
        ? elips_resolve_category_id((int) $data['categoryId'], $category)
        : elips_resolve_category_id($course['category_id'] === null ? null : (int) $course['category_id'], $category);

    $changes = [];
    if ($course['title'] !== $title) $changes[] = 'judul';
    if ($course['category'] !== $category) $changes[] = 'kategori';
    if ((string) $course['description'] !== $description) $changes[] = 'deskripsi';
    if ($previousInstructorId !== $nextInstructorId) $changes[] = 'instruktur';

    $stmt = elips_db()->prepare(
        'UPDATE courses SET title = ?, category = ?, category_id = ?, description = ?, instructor_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([$title, $category, $categoryId, $description, $nextInstructorId, $id]);

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

    $db = elips_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM classes WHERE course_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return elips_fail('Kursus tidak dapat dihapus karena sudah memiliki kelas/angkatan. Arsipkan kursus atau selesaikan kelas terlebih dahulu.');
    }
    $stmt = $db->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return elips_fail('Kursus tidak dapat dihapus karena masih memiliki enrollment peserta.');
    }
    $stmt = $db->prepare('SELECT COUNT(*) FROM certificates WHERE course_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return elips_fail('Kursus tidak dapat dihapus karena memiliki riwayat sertifikat.');
    }

    $stmt = $db->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'course',
        $id,
        $course['title'],
        'Menghapus kursus "' . $course['title'] . '" beserta versi kurikulum, modul, dan materi terkait.',
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
        $version = elips_ensure_curriculum_version($id, (int) $actor['id']);
        $stmt = elips_db()->prepare('SELECT COUNT(*) FROM modules WHERE curriculum_version_id = ?');
        $stmt->execute([(int) $version['id']]);
        $moduleCount = (int) $stmt->fetchColumn();
        $stmt = elips_db()->prepare('SELECT COUNT(*) FROM lessons WHERE curriculum_version_id = ?');
        $stmt->execute([(int) $version['id']]);
        $lessonCount = (int) $stmt->fetchColumn();
        if ($moduleCount === 0 || $lessonCount === 0) {
            return elips_fail('Tambahkan minimal satu modul dan satu materi sebelum publikasi.');
        }
        if ($version['status'] === 'draft') {
            $stmt = elips_db()->prepare(
                "UPDATE curriculum_versions SET status = 'published', updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?"
            );
            $stmt->execute([(int) $actor['id'], (int) $version['id']]);
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

// ---------- Curriculum versions ----------

function elips_action_curriculum_create(array $p): array
{
    $actor = elips_require_admin();
    $courseId = (int) ($p['courseId'] ?? 0);
    $course = elips_course_row($courseId);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');

    $code = trim((string) ($p['code'] ?? ''));
    if ($code === '') $code = elips_next_curriculum_code($courseId);
    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') $name = 'Kurikulum ' . $code . ' — ' . $course['title'];

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM curriculum_versions WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->execute([$courseId, $code]);
    if ($stmt->fetch()) return elips_fail('Kode versi kurikulum sudah dipakai pada kursus ini.');

    $effectiveDate = elips_normalize_date($p['effectiveDate'] ?? null);
    if (trim((string) ($p['effectiveDate'] ?? '')) !== '' && $effectiveDate === null) {
        return elips_fail('Tanggal efektif tidak valid (format YYYY-MM-DD).');
    }

    $stmt = $db->prepare(
        "INSERT INTO curriculum_versions
         (course_id, code, name, description, status, effective_date, notes, created_by, updated_by)
         VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?)"
    );
    $stmt->execute([
        $courseId,
        $code,
        $name,
        trim((string) ($p['description'] ?? '')),
        $effectiveDate,
        trim((string) ($p['notes'] ?? '')),
        (int) $actor['id'],
        (int) $actor['id'],
    ]);
    $id = (int) $db->lastInsertId();

    elips_audit(
        'create',
        'curriculum',
        $id,
        $name,
        'Membuat versi kurikulum "' . $name . '" (' . $code . ') untuk kursus "' . $course['title'] . '".',
        $actor
    );

    return elips_ok('Versi kurikulum baru berhasil dibuat sebagai Draft.', elips_curriculum_public($id));
}

function elips_action_curriculum_update(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $data = elips_payload_array($p, 'data');
    $version = elips_curriculum_version_row($id);
    if (!$version) return elips_fail('Versi kurikulum tidak ditemukan.');
    if ($version['status'] !== 'draft') {
        return elips_fail('Hanya versi kurikulum berstatus Draft yang dapat diubah. Buat versi baru untuk revisi.');
    }

    $code = array_key_exists('code', $data) ? trim((string) $data['code']) : $version['code'];
    $name = array_key_exists('name', $data) ? trim((string) $data['name']) : $version['name'];
    $description = array_key_exists('description', $data) ? trim((string) $data['description']) : $version['description'];
    $notes = array_key_exists('notes', $data) ? trim((string) $data['notes']) : $version['notes'];
    $effectiveDate = array_key_exists('effectiveDate', $data)
        ? elips_normalize_date($data['effectiveDate'])
        : elips_date_only($version['effective_date']);

    if ($code === '') return elips_fail('Kode versi wajib diisi.');
    if ($name === '') return elips_fail('Nama versi wajib diisi.');
    if (array_key_exists('effectiveDate', $data) && trim((string) $data['effectiveDate']) !== '' && $effectiveDate === null) {
        return elips_fail('Tanggal efektif tidak valid (format YYYY-MM-DD).');
    }

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM curriculum_versions WHERE course_id = ? AND code = ? AND id <> ? LIMIT 1');
    $stmt->execute([(int) $version['course_id'], $code, $id]);
    if ($stmt->fetch()) return elips_fail('Kode versi kurikulum sudah dipakai pada kursus ini.');

    $stmt = $db->prepare(
        'UPDATE curriculum_versions
         SET code = ?, name = ?, description = ?, effective_date = ?, notes = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([$code, $name, $description, $effectiveDate, $notes, (int) $actor['id'], $id]);

    elips_audit(
        'update',
        'curriculum',
        $id,
        $name,
        'Memperbarui versi kurikulum "' . $name . '" (' . $code . ').',
        $actor
    );

    return elips_ok('Versi kurikulum berhasil diperbarui.', elips_curriculum_public($id));
}

function elips_action_curriculum_publish(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $version = elips_curriculum_version_row($id);
    if (!$version) return elips_fail('Versi kurikulum tidak ditemukan.');
    if ($version['status'] !== 'draft') return elips_fail('Hanya versi Draft yang dapat dipublikasikan.');

    $db = elips_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM modules WHERE curriculum_version_id = ?');
    $stmt->execute([$id]);
    $moduleCount = (int) $stmt->fetchColumn();
    $stmt = $db->prepare('SELECT COUNT(*) FROM lessons WHERE curriculum_version_id = ?');
    $stmt->execute([$id]);
    $lessonCount = (int) $stmt->fetchColumn();
    if ($moduleCount === 0 || $lessonCount === 0) {
        return elips_fail('Tambahkan minimal satu modul dan satu materi sebelum mempublikasikan versi.');
    }

    $stmt = $db->prepare("UPDATE curriculum_versions SET status = 'published', updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
    $stmt->execute([(int) $actor['id'], $id]);

    elips_audit(
        'publish',
        'curriculum',
        $id,
        $version['name'],
        'Mempublikasikan versi kurikulum "' . $version['name'] . '" (' . $version['code'] . ').',
        $actor
    );

    return elips_ok('Versi kurikulum berhasil dipublikasikan.', elips_curriculum_public($id));
}

function elips_action_curriculum_clone(array $p): array
{
    $actor = elips_require_admin();
    $sourceId = (int) ($p['id'] ?? $p['sourceId'] ?? 0);
    $source = elips_curriculum_version_row($sourceId);
    if (!$source) return elips_fail('Versi kurikulum sumber tidak ditemukan.');

    $courseId = (int) $source['course_id'];
    $code = trim((string) ($p['code'] ?? ''));
    if ($code === '') $code = elips_next_curriculum_code($courseId);
    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') $name = 'Kurikulum ' . $code . ' — revisi dari ' . $source['code'];

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM curriculum_versions WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->execute([$courseId, $code]);
    if ($stmt->fetch()) return elips_fail('Kode versi kurikulum sudah dipakai pada kursus ini.');

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO curriculum_versions
             (course_id, code, name, description, status, effective_date, notes, created_by, updated_by)
             VALUES (?, ?, ?, ?, 'draft', NULL, ?, ?, ?)"
        );
        $stmt->execute([
            $courseId,
            $code,
            $name,
            $source['description'],
            'Disalin dari versi ' . $source['code'] . '.',
            (int) $actor['id'],
            (int) $actor['id'],
        ]);
        $newId = (int) $db->lastInsertId();

        $moduleStmt = $db->prepare(
            'INSERT INTO modules (course_id, curriculum_version_id, instructor_id, title, description, sort_order, created_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $lessonStmt = $db->prepare(
            'INSERT INTO lessons (module_id, course_id, curriculum_version_id, instructor_id, title, type, content, sort_order, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );

        $stmt = $db->prepare('SELECT * FROM modules WHERE curriculum_version_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$sourceId]);
        $modules = $stmt->fetchAll();
        foreach ($modules as $module) {
            $moduleStmt->execute([
                $courseId,
                $newId,
                $module['instructor_id'],
                $module['title'],
                $module['description'],
                (int) $module['sort_order'],
            ]);
            $newModuleId = (int) $db->lastInsertId();

            $lessonQuery = $db->prepare('SELECT * FROM lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC');
            $lessonQuery->execute([(int) $module['id']]);
            foreach ($lessonQuery->fetchAll() as $lesson) {
                $lessonStmt->execute([
                    $newModuleId,
                    $courseId,
                    $newId,
                    $lesson['instructor_id'],
                    $lesson['title'],
                    $lesson['type'],
                    $lesson['content'],
                    (int) $lesson['sort_order'],
                ]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    elips_audit(
        'clone',
        'curriculum',
        $newId,
        $name,
        'Membuat versi kurikulum "' . $name . '" (' . $code . ') sebagai salinan Draft dari versi ' . $source['code'] . '.',
        $actor
    );

    return elips_ok('Versi kurikulum baru berhasil disalin sebagai Draft.', elips_curriculum_public($newId));
}

function elips_action_curriculum_retire(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $version = elips_curriculum_version_row($id);
    if (!$version) return elips_fail('Versi kurikulum tidak ditemukan.');
    if ($version['status'] === 'retired') return elips_fail('Versi kurikulum sudah berstatus Retired.');
    if ($version['status'] === 'draft') return elips_fail('Versi Draft dapat dihapus, bukan dipensiunkan.');

    $stmt = elips_db()->prepare("UPDATE curriculum_versions SET status = 'retired', updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
    $stmt->execute([(int) $actor['id'], $id]);

    elips_audit(
        'retire',
        'curriculum',
        $id,
        $version['name'],
        'Memensiunkan versi kurikulum "' . $version['name'] . '" (' . $version['code'] . '). Riwayat kelas tetap tersedia.',
        $actor
    );

    return elips_ok('Versi kurikulum dipensiunkan. Kelas lama tetap menggunakan versi ini.', elips_curriculum_public($id));
}

function elips_action_curriculum_delete(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $version = elips_curriculum_version_row($id);
    if (!$version) return elips_fail('Versi kurikulum tidak ditemukan.');
    if ($version['status'] !== 'draft') {
        return elips_fail('Hanya versi Draft tanpa kelas yang dapat dihapus. Versi terpakai harus dipensiunkan.');
    }
    $db = elips_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM classes WHERE curriculum_version_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return elips_fail('Versi kurikulum sudah dipakai kelas dan tidak dapat dihapus.');
    }

    $stmt = $db->prepare('DELETE FROM curriculum_versions WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'curriculum',
        $id,
        $version['name'],
        'Menghapus versi kurikulum Draft "' . $version['name'] . '" (' . $version['code'] . ').',
        $actor
    );

    return elips_ok('Versi kurikulum Draft berhasil dihapus.');
}

function elips_action_curriculum_detail(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $version = elips_curriculum_version_row($id);
    if (!$version) return elips_fail('Versi kurikulum tidak ditemukan.');
    $course = elips_course_row((int) $version['course_id']);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');
    if ($actor['role'] === 'instruktur') {
        $stmt = elips_db()->prepare('SELECT COUNT(*) FROM classes WHERE course_id = ? AND instructor_id = ?');
        $stmt->execute([(int) $version['course_id'], (int) $actor['id']]);
        $assigned = (int) $stmt->fetchColumn() > 0;
        if (!elips_can_manage_course($course, $actor) && !$assigned) {
            return elips_fail('Anda tidak berhak melihat kurikulum ini.');
        }
    } elseif ($actor['role'] === 'peserta') {
        $stmt = elips_db()->prepare(
            "SELECT COUNT(*) FROM enrollments e JOIN classes cl ON cl.id = e.class_id
             WHERE e.student_id = ? AND cl.curriculum_version_id = ?"
        );
        $stmt->execute([(int) $actor['id'], $id]);
        if ((int) $stmt->fetchColumn() === 0) {
            return elips_fail('Anda tidak berhak melihat kurikulum ini.');
        }
    }

    $modules = [];
    $db = elips_db();
    $stmt = $db->prepare('SELECT * FROM modules WHERE curriculum_version_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$id]);
    $moduleRows = $stmt->fetchAll();
    foreach ($moduleRows as $module) {
        $stmt = $db->prepare('SELECT * FROM lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([(int) $module['id']]);
        $lessons = [];
        foreach ($stmt->fetchAll() as $lesson) {
            $lessons[] = [
                'id' => (int) $lesson['id'],
                'moduleId' => (int) $lesson['module_id'],
                'courseId' => (int) $lesson['course_id'],
                'curriculumVersionId' => (int) $lesson['curriculum_version_id'],
                'title' => $lesson['title'],
                'type' => $lesson['type'],
                'content' => $lesson['content'],
                'order' => (int) $lesson['sort_order'],
            ];
        }
        $modules[] = [
            'id' => (int) $module['id'],
            'courseId' => (int) $module['course_id'],
            'curriculumVersionId' => (int) $module['curriculum_version_id'],
            'title' => $module['title'],
            'description' => $module['description'],
            'order' => (int) $module['sort_order'],
            'lessons' => $lessons,
        ];
    }

    $data = elips_curriculum_public($id);
    $data['modules'] = $modules;
    return elips_ok('Detail kurikulum dimuat.', $data);
}

// ---------- Classes / cohorts ----------

function elips_generate_class_code(int $courseId, string $name): string
{
    $db = elips_db();
    $prefix = 'ANG-' . gmdate('Y') . '-' . $courseId;
    $base = $prefix . '-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]+/', '', $name) ?: 'KLS', 0, 4));
    $code = $base;
    $suffix = 2;
    $stmt = $db->prepare('SELECT COUNT(*) FROM classes WHERE code = ?');
    while (true) {
        $stmt->execute([$code]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
        $code = $base . '-' . $suffix++;
    }
}

function elips_action_class_create(array $p): array
{
    $actor = elips_require_admin();
    $courseId = (int) ($p['courseId'] ?? 0);
    $course = elips_course_row($courseId);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');

    $versionId = (int) ($p['curriculumVersionId'] ?? 0);
    $version = elips_curriculum_version_row($versionId);
    if (!$version || (int) $version['course_id'] !== $courseId) {
        return elips_fail('Versi kurikulum tidak valid untuk kursus ini.');
    }
    if (!in_array($version['status'], ['published', 'locked'], true)) {
        return elips_fail('Pilih versi kurikulum yang sudah Published.');
    }

    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') return elips_fail('Nama kelas wajib diisi.');
    $code = trim((string) ($p['code'] ?? ''));
    if ($code === '') $code = elips_generate_class_code($courseId, $name);

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM classes WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    if ($stmt->fetch()) return elips_fail('Kode kelas sudah dipakai.');

    $instructorId = isset($p['instructorId']) ? (int) $p['instructorId'] : 0;
    if ($instructorId > 0 && !elips_is_active_instructor($instructorId)) {
        return elips_fail('Tentor harus berstatus aktif.');
    }

    $branchId = isset($p['branchId']) && $p['branchId'] !== '' ? (int) $p['branchId'] : 0;
    if ($branchId > 0) {
        $branch = elips_branch_row($branchId);
        if (!$branch) return elips_fail('Cabang tidak ditemukan.');
        if ($branch['status'] !== 'active') return elips_fail('Cabang harus berstatus aktif.');
    }

    $periodStart = elips_normalize_date($p['periodStart'] ?? null);
    $periodEnd = elips_normalize_date($p['periodEnd'] ?? null);
    if (trim((string) ($p['periodStart'] ?? '')) !== '' && $periodStart === null) return elips_fail('Tanggal mulai tidak valid.');
    if (trim((string) ($p['periodEnd'] ?? '')) !== '' && $periodEnd === null) return elips_fail('Tanggal selesai tidak valid.');
    if ($periodStart !== null && $periodEnd !== null && $periodStart > $periodEnd) {
        return elips_fail('Tanggal selesai tidak boleh sebelum tanggal mulai.');
    }

    $status = (string) ($p['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'open'], true)) {
        return elips_fail('Kelas baru hanya dapat berstatus Draft atau Open.');
    }
    $quota = max(0, (int) ($p['quota'] ?? 0));
    $plannedJp = max(0, (float) ($p['plannedJp'] ?? 0));

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO classes
             (code, name, course_id, curriculum_version_id, branch_id, instructor_id, period_start, period_end, quota,
              location, room, schedule_note, planned_jp, status, legacy_import, notes, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)"
        );
        $stmt->execute([
            $code,
            $name,
            $courseId,
            $versionId,
            $branchId > 0 ? $branchId : null,
            $instructorId > 0 ? $instructorId : null,
            $periodStart,
            $periodEnd,
            $quota,
            trim((string) ($p['location'] ?? '')),
            trim((string) ($p['room'] ?? '')),
            trim((string) ($p['scheduleNote'] ?? '')),
            $plannedJp,
            $status,
            trim((string) ($p['notes'] ?? '')),
            (int) $actor['id'],
            (int) $actor['id'],
        ]);
        $id = (int) $db->lastInsertId();

        // Versi kurikulum dikunci agar perubahan berikutnya tidak
        // mengubah kurikulum kelas yang sudah berjalan.
        $stmt = $db->prepare(
            "UPDATE curriculum_versions SET status = 'locked', updated_by = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = 'published'"
        );
        $stmt->execute([(int) $actor['id'], $versionId]);

        if ($instructorId > 0) {
            $stmt = $db->prepare(
                'INSERT INTO class_instructor_histories (class_id, instructor_id, started_at, ended_at, reason, actor_id)
                 VALUES (?, ?, ?, NULL, ?, ?)'
            );
            $stmt->execute([$id, $instructorId, $periodStart ?: gmdate('Y-m-d'), 'Penetapan tentor awal saat kelas dibuat.', (int) $actor['id']]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    elips_audit(
        'create',
        'class',
        $id,
        $name,
        'Membuat kelas "' . $name . '" (' . $code . ') untuk kursus "' . $course['title'] . '" dengan kurikulum versi ' . $version['code'] . '.',
        $actor
    );

    return elips_ok('Kelas berhasil dibuat dan versi kurikulum dikunci untuk kelas ini.', elips_class_public($id));
}

function elips_action_class_update(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $data = elips_payload_array($p, 'data');
    $class = elips_class_row($id);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if ($class['status'] === 'archived') return elips_fail('Kelas yang sudah diarsipkan tidak dapat diubah.');

    $name = array_key_exists('name', $data) ? trim((string) $data['name']) : $class['name'];
    if ($name === '') return elips_fail('Nama kelas wajib diisi.');

    $periodStart = array_key_exists('periodStart', $data) ? elips_normalize_date($data['periodStart']) : elips_date_only($class['period_start']);
    $periodEnd = array_key_exists('periodEnd', $data) ? elips_normalize_date($data['periodEnd']) : elips_date_only($class['period_end']);
    if (array_key_exists('periodStart', $data) && trim((string) $data['periodStart']) !== '' && $periodStart === null) {
        return elips_fail('Tanggal mulai tidak valid.');
    }
    if (array_key_exists('periodEnd', $data) && trim((string) $data['periodEnd']) !== '' && $periodEnd === null) {
        return elips_fail('Tanggal selesai tidak valid.');
    }
    if ($periodStart !== null && $periodEnd !== null && $periodStart > $periodEnd) {
        return elips_fail('Tanggal selesai tidak boleh sebelum tanggal mulai.');
    }

    $quota = array_key_exists('quota', $data) ? max(0, (int) $data['quota']) : (int) $class['quota'];
    $activeCount = elips_class_enrollment_count($id, true);
    if ($quota > 0 && $quota < $activeCount) {
        return elips_fail('Kuota tidak boleh lebih kecil dari jumlah peserta aktif (' . $activeCount . ').');
    }

    $instructorId = array_key_exists('instructorId', $data)
        ? (int) $data['instructorId']
        : ($class['instructor_id'] === null ? 0 : (int) $class['instructor_id']);
    if ($instructorId > 0 && !elips_is_active_instructor($instructorId)) {
        return elips_fail('Tentor harus berstatus aktif.');
    }

    $branchId = array_key_exists('branchId', $data)
        ? (int) $data['branchId']
        : ($class['branch_id'] === null ? 0 : (int) $class['branch_id']);
    if ($branchId > 0) {
        $branch = elips_branch_row($branchId);
        if (!$branch) return elips_fail('Cabang tidak ditemukan.');
        if ($branch['status'] !== 'active') return elips_fail('Cabang harus berstatus aktif.');
    }

    $db = elips_db();
    $stmt = $db->prepare(
        'UPDATE classes
         SET name = ?, branch_id = ?, period_start = ?, period_end = ?, quota = ?, location = ?, room = ?,
             schedule_note = ?, planned_jp = ?, notes = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([
        $name,
        $branchId > 0 ? $branchId : null,
        $periodStart,
        $periodEnd,
        $quota,
        array_key_exists('location', $data) ? trim((string) $data['location']) : $class['location'],
        array_key_exists('room', $data) ? trim((string) $data['room']) : $class['room'],
        array_key_exists('scheduleNote', $data) ? trim((string) $data['scheduleNote']) : $class['schedule_note'],
        array_key_exists('plannedJp', $data) ? max(0, (float) $data['plannedJp']) : (float) $class['planned_jp'],
        array_key_exists('notes', $data) ? trim((string) $data['notes']) : $class['notes'],
        (int) $actor['id'],
        $id,
    ]);

    $previousInstructor = $class['instructor_id'] === null ? null : (int) $class['instructor_id'];
    if ($instructorId !== ($previousInstructor ?? 0)) {
        elips_apply_class_instructor($id, $instructorId > 0 ? $instructorId : null, (string) ($data['instructorReason'] ?? 'Pergantian tentor melalui pembaruan kelas.'), $actor);
    }

    elips_audit(
        'update',
        'class',
        $id,
        $name,
        'Memperbarui kelas "' . $name . '" (' . $class['code'] . ').',
        $actor
    );

    return elips_ok('Kelas berhasil diperbarui.', elips_class_public($id));
}

function elips_apply_class_instructor(int $classId, ?int $instructorId, string $reason, array $actor): void
{
    $db = elips_db();
    $class = elips_class_row($classId);
    if (!$class) return;
    $today = gmdate('Y-m-d');

    $stmt = $db->prepare(
        'UPDATE class_instructor_histories SET ended_at = ?
         WHERE class_id = ? AND ended_at IS NULL'
    );
    $stmt->execute([$today, $classId]);

    $stmt = $db->prepare('UPDATE classes SET instructor_id = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$instructorId, (int) $actor['id'], $classId]);

    if ($instructorId !== null) {
        $stmt = $db->prepare(
            'INSERT INTO class_instructor_histories (class_id, instructor_id, started_at, ended_at, reason, actor_id)
             VALUES (?, ?, ?, NULL, ?, ?)'
        );
        $stmt->execute([$classId, $instructorId, $today, $reason, (int) $actor['id']]);
    }
}

function elips_action_class_assign_instructor(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $instructorId = (int) ($p['instructorId'] ?? 0);
    $class = elips_class_row($id);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_is_active_instructor($instructorId)) return elips_fail('Pilih tentor aktif yang valid.');
    if ($class['instructor_id'] !== null && (int) $class['instructor_id'] === $instructorId) {
        return elips_ok('Tentor tersebut sudah ditugaskan pada kelas ini.', elips_class_public($id));
    }

    $reason = trim((string) ($p['reason'] ?? ''));
    if ($reason === '') $reason = 'Pergantian tentor oleh Admin.';

    $db = elips_db();
    $db->beginTransaction();
    try {
        elips_apply_class_instructor($id, $instructorId, $reason, $actor);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $instructor = elips_user_row($instructorId);
    elips_audit(
        'assign',
        'class',
        $id,
        $class['name'],
        'Menetapkan "' . ($instructor ? $instructor['name'] : 'Tentor #' . $instructorId) . '" sebagai tentor kelas "' . $class['name'] . '". Alasan: ' . $reason,
        $actor
    );

    return elips_ok('Tentor kelas berhasil ditetapkan.', elips_class_public($id));
}

function elips_action_class_status(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $status = (string) ($p['status'] ?? '');
    $allowed = ['draft', 'open', 'ready', 'ongoing', 'completed', 'cancelled', 'archived'];
    if (!in_array($status, $allowed, true)) return elips_fail('Status kelas tidak valid.');

    $class = elips_class_row($id);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (in_array($status, ['ready', 'ongoing'], true) && !elips_is_active_instructor($class['instructor_id'] === null ? null : (int) $class['instructor_id'])) {
        return elips_fail('Tetapkan tentor aktif sebelum kelas berstatus Ready atau Ongoing.');
    }

    $stmt = elips_db()->prepare('UPDATE classes SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$status, (int) $actor['id'], $id]);

    elips_audit(
        'status',
        'class',
        $id,
        $class['name'],
        'Mengubah status kelas "' . $class['name'] . '" dari ' . $class['status'] . ' menjadi ' . $status . '.',
        $actor
    );

    return elips_ok('Status kelas diperbarui menjadi ' . $status . '.', elips_class_public($id));
}

function elips_action_class_delete(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $class = elips_class_row($id);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!in_array($class['status'], ['draft', 'cancelled'], true)) {
        return elips_fail('Hanya kelas Draft atau Cancelled tanpa peserta yang dapat dihapus.');
    }
    if (elips_class_enrollment_count($id, false) > 0) {
        return elips_fail('Kelas sudah memiliki enrollment. Ubah status kelas atau arsipkan, jangan hapus permanen.');
    }

    $stmt = elips_db()->prepare('DELETE FROM classes WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'class',
        $id,
        $class['name'],
        'Menghapus kelas "' . $class['name'] . '" (' . $class['code'] . ') yang belum memiliki peserta.',
        $actor
    );

    return elips_ok('Kelas berhasil dihapus.');
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

        // Kompatibilitas v3: enrollment berbasis kursus diarahkan ke kelas
        // aktif yang masih memiliki kuota bila tersedia.
        $stmt = $db->prepare(
            "SELECT cl.id FROM classes cl
             WHERE cl.course_id = ? AND cl.status IN ('open', 'ready', 'ongoing')
               AND (cl.quota = 0 OR (SELECT COUNT(*) FROM enrollments e
                    WHERE e.class_id = cl.id AND e.status IN ('active', 'pending')) < cl.quota)
             ORDER BY cl.id ASC LIMIT 1"
        );
        $stmt->execute([$courseId]);
        $classId = $stmt->fetchColumn();
        $classId = $classId === false ? null : (int) $classId;

        $stmt = $db->prepare(
            "INSERT INTO enrollments (student_id, course_id, class_id, enrolled_by, enrolled_at, status, created_at)
             VALUES (?, ?, ?, 'admin', ?, 'active', UTC_TIMESTAMP())"
        );
        $stmt->execute([$studentId, $courseId, $classId, $enrolledAt]);
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

function elips_action_enrollment_assign_class(array $p): array
{
    $actor = elips_require_admin();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (in_array($class['status'], ['cancelled', 'archived'], true)) {
        return elips_fail('Kelas ini tidak menerima pendaftaran peserta.');
    }

    $requested = [];
    if (isset($p['studentIds']) && is_array($p['studentIds'])) {
        foreach ($p['studentIds'] as $studentId) {
            $studentId = (int) $studentId;
            if ($studentId > 0) $requested[$studentId] = true;
        }
    }
    if (isset($p['studentId'])) {
        $studentId = (int) $p['studentId'];
        if ($studentId > 0) $requested[$studentId] = true;
    }
    if (!$requested) return elips_fail('Pilih minimal satu peserta.');

    $db = elips_db();
    $quota = (int) $class['quota'];
    $activeCount = elips_class_enrollment_count($classId, true);

    $enrolledAt = trim((string) ($p['enrolledAt'] ?? ''));
    if ($enrolledAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $enrolledAt)) {
        $enrolledAt = gmdate('Y-m-d');
    }

    $added = 0;
    $skipped = 0;
    foreach (array_keys($requested) as $studentId) {
        $student = elips_user_row($studentId);
        if (!$student || $student['role'] !== 'peserta') return elips_fail('Peserta #' . $studentId . ' tidak valid.');
        if ($student['status'] !== 'aktif') return elips_fail('Peserta "' . $student['name'] . '" tidak aktif.');

        $stmt = $db->prepare('SELECT id FROM enrollments WHERE student_id = ? AND class_id = ? LIMIT 1');
        $stmt->execute([$studentId, $classId]);
        if ($stmt->fetch()) {
            $skipped++;
            continue;
        }
        if ($quota > 0 && $activeCount >= $quota) {
            return elips_fail('Kuota kelas sudah penuh (' . $quota . ' peserta).');
        }

        $stmt = $db->prepare(
            "INSERT INTO enrollments (student_id, course_id, class_id, enrolled_by, enrolled_at, status, created_at)
             VALUES (?, ?, ?, 'admin', ?, 'active', UTC_TIMESTAMP())"
        );
        $stmt->execute([$studentId, (int) $class['course_id'], $classId, $enrolledAt]);
        $enrollmentId = (int) $db->lastInsertId();
        $added++;
        $activeCount++;

        elips_audit(
            'create',
            'enrollment',
            $enrollmentId,
            $student['name'] . ' — ' . $class['name'],
            'Mendaftarkan "' . $student['name'] . '" ke kelas "' . $class['name'] . '" (' . $class['code'] . ').',
            $actor
        );
    }

    return elips_ok(
        $added > 0
            ? $added . ' peserta berhasil didaftarkan ke kelas.' . ($skipped > 0 ? ' ' . $skipped . ' peserta sudah terdaftar.' : '')
            : 'Semua peserta yang dipilih sudah terdaftar di kelas ini.',
        ['added' => $added, 'skipped' => $skipped]
    );
}

function elips_action_enrollment_class_status(array $p): array
{
    $actor = elips_require_admin();
    $id = (int) ($p['id'] ?? 0);
    $status = (string) ($p['status'] ?? '');
    $allowed = ['pending', 'active', 'completed', 'withdrawn', 'suspended', 'cancelled', 'dihentikan'];
    if (!in_array($status, $allowed, true)) {
        return elips_fail('Status enrollment tidak valid.');
    }
    $enrollment = elips_enrollment_row($id);
    if (!$enrollment) return elips_fail('Enrollment tidak ditemukan.');
    if ($enrollment['class_id'] === null) {
        return elips_fail('Enrollment ini bukan enrollment berbasis kelas.');
    }

    $stmt = elips_db()->prepare('UPDATE enrollments SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$status, $id]);

    $student = elips_user_row((int) $enrollment['student_id']);
    $class = elips_class_row((int) $enrollment['class_id']);
    $label = ($student ? $student['name'] : 'Peserta #' . $enrollment['student_id'])
        . ' — ' . ($class ? $class['name'] : 'Kelas #' . $enrollment['class_id']);

    elips_audit(
        'status',
        'enrollment',
        $id,
        $label,
        'Mengubah status enrollment "' . $label . '" menjadi ' . $status . '.',
        $actor
    );

    return elips_ok('Status enrollment diperbarui menjadi ' . $status . '.', elips_enrollment_public($id));
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
        if ($enrollment['class_id'] === null) {
            $stmt = $db->prepare('DELETE FROM grades WHERE student_id = ? AND course_id = ?');
            $stmt->execute([$studentId, $courseId]);
            $stmt = $db->prepare('DELETE FROM certificates WHERE student_id = ? AND course_id = ?');
            $stmt->execute([$studentId, $courseId]);
            $stmt = $db->prepare('DELETE FROM completed_lessons WHERE student_id = ? AND course_id = ?');
            $stmt->execute([$studentId, $courseId]);
        } else {
            $stmt = $db->prepare('DELETE FROM grades WHERE enrollment_id = ?');
            $stmt->execute([$id]);
            $stmt = $db->prepare('DELETE FROM certificates WHERE enrollment_id = ?');
            $stmt->execute([$id]);
        }
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

// ---------- Class sessions & attendance ----------

function elips_session_statuses(): array
{
    return ['scheduled', 'confirmed', 'completed', 'rescheduled', 'cancelled', 'no_show'];
}

function elips_attendance_statuses(): array
{
    return ['present', 'late', 'permission', 'sick', 'absent', 'excused'];
}

function elips_session_time_value($value, string $label): string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        throw new ElipsError($label . ' wajib diisi.');
    }
    $normalized = elips_time_normalized($text);
    if ($normalized === null) {
        throw new ElipsError('Format ' . strtolower($label) . ' tidak valid (JJ:MM).');
    }
    return $normalized;
}

function elips_session_next_number(int $classId): int
{
    $stmt = elips_db()->prepare('SELECT COALESCE(MAX(session_number), 0) FROM class_sessions WHERE class_id = ?');
    $stmt->execute([$classId]);
    return (int) $stmt->fetchColumn() + 1;
}

function elips_session_normalize(array $class, array $p, int $sessionNumber): array
{
    $date = trim((string) ($p['sessionDate'] ?? ''));
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)
        || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
        throw new ElipsError('Tanggal sesi tidak valid.');
    }

    $start = elips_session_time_value($p['startTime'] ?? null, 'Waktu mulai');
    $end = elips_session_time_value($p['endTime'] ?? null, 'Waktu selesai');
    if ($end <= $start) {
        throw new ElipsError('Waktu selesai harus setelah waktu mulai.');
    }

    $moduleId = (int) ($p['moduleId'] ?? 0);
    if ($moduleId > 0) {
        $stmt = elips_db()->prepare('SELECT * FROM modules WHERE id = ? LIMIT 1');
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();
        if (!$module
            || (int) $module['course_id'] !== (int) $class['course_id']
            || (int) $module['curriculum_version_id'] !== (int) $class['curriculum_version_id']) {
            throw new ElipsError('Modul sesi tidak sesuai dengan kurikulum kelas.');
        }
    }

    $instructorId = array_key_exists('instructorId', $p)
        ? (int) $p['instructorId']
        : (int) ($class['instructor_id'] ?? 0);
    if ($instructorId <= 0) {
        $instructorId = (int) ($class['instructor_id'] ?? 0);
    }
    if ($instructorId > 0 && !elips_is_active_instructor($instructorId)) {
        throw new ElipsError('Instruktur sesi harus berstatus aktif.');
    }

    $plannedJp = max(0, (float) ($p['plannedJp'] ?? 0));
    $actualJp = max(0, (float) ($p['actualJp'] ?? 0));
    $status = (string) ($p['status'] ?? 'scheduled');
    if (!in_array($status, elips_session_statuses(), true)) {
        throw new ElipsError('Status sesi tidak valid.');
    }
    if ($status === 'completed' && $actualJp <= 0) {
        $actualJp = $plannedJp;
    }

    $title = trim((string) ($p['title'] ?? ''));
    if ($title === '') {
        $title = 'Pertemuan ' . $sessionNumber;
    }

    return [
        'title' => $title,
        'moduleId' => $moduleId > 0 ? $moduleId : null,
        'sessionDate' => $date,
        'startTime' => $start,
        'endTime' => $end,
        'location' => array_key_exists('location', $p) ? trim((string) $p['location']) : (string) $class['location'],
        'room' => array_key_exists('room', $p) ? trim((string) $p['room']) : (string) $class['room'],
        'instructorId' => $instructorId > 0 ? $instructorId : null,
        'plannedJp' => round($plannedJp, 2),
        'actualJp' => round($actualJp, 2),
        'status' => $status,
        'notes' => trim((string) ($p['notes'] ?? '')),
    ];
}

function elips_session_store(array $class, array $p, array $actor, int $sessionNumber): int
{
    $values = elips_session_normalize($class, $p, $sessionNumber);
    $rescheduledFromId = (int) ($p['rescheduledFromId'] ?? 0);
    $original = null;
    if ($rescheduledFromId > 0) {
        $original = elips_session_row($rescheduledFromId);
        if (!$original || (int) $original['class_id'] !== (int) $class['id']) {
            throw new ElipsError('Sesi asal penjadwalan ulang tidak ditemukan pada kelas ini.');
        }
        if ($original['status'] === 'completed') {
            throw new ElipsError('Sesi yang sudah selesai tidak dapat dijadwalkan ulang.');
        }
    }

    $stmt = elips_db()->prepare(
        'INSERT INTO class_sessions
         (class_id, session_number, title, module_id, session_date, start_time, end_time,
          location, room, instructor_id, planned_jp, actual_jp, status, rescheduled_from_id,
          notes, created_by, updated_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        (int) $class['id'],
        $sessionNumber,
        $values['title'],
        $values['moduleId'],
        $values['sessionDate'],
        $values['startTime'],
        $values['endTime'],
        $values['location'],
        $values['room'],
        $values['instructorId'],
        $values['plannedJp'],
        $values['actualJp'],
        $values['status'],
        $original !== null ? (int) $original['id'] : null,
        $values['notes'],
        (int) $actor['id'],
        (int) $actor['id'],
    ]);
    $id = (int) elips_db()->lastInsertId();

    if ($original !== null) {
        $stmt = elips_db()->prepare(
            "UPDATE class_sessions SET status = 'rescheduled', updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?"
        );
        $stmt->execute([(int) $actor['id'], (int) $original['id']]);
    }

    elips_audit(
        'create',
        'session',
        $id,
        $class['name'] . ' — Pertemuan ' . $sessionNumber,
        'Menambahkan sesi pertemuan ' . $sessionNumber . ' untuk kelas "' . $class['name'] . '" pada '
            . $values['sessionDate'] . ' pukul ' . substr($values['startTime'], 0, 5)
            . '-' . substr($values['endTime'], 0, 5) . ' (' . $values['plannedJp'] . ' JP).',
        $actor
    );

    return $id;
}

function elips_action_session_create(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak mengelola jadwal kelas ini.');
    }
    if (in_array($class['status'], ['cancelled', 'archived'], true)) {
        return elips_fail('Kelas ini tidak dapat dijadwalkan.');
    }

    $db = elips_db();
    $db->beginTransaction();
    try {
        $requestedNumber = (int) ($p['sessionNumber'] ?? 0);
        if ($requestedNumber > 0) {
            $stmt = $db->prepare('SELECT id FROM class_sessions WHERE class_id = ? AND session_number = ? LIMIT 1');
            $stmt->execute([$classId, $requestedNumber]);
            if ($stmt->fetch()) {
                throw new ElipsError('Nomor pertemuan ' . $requestedNumber . ' sudah digunakan pada kelas ini.');
            }
            $sessionNumber = $requestedNumber;
        } else {
            $sessionNumber = elips_session_next_number($classId);
        }
        $id = elips_session_store($class, $p, $actor, $sessionNumber);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return elips_ok('Sesi pertemuan berhasil dibuat.', elips_session_public($id));
}

function elips_action_session_create_bulk(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak mengelola jadwal kelas ini.');
    }
    if (in_array($class['status'], ['cancelled', 'archived'], true)) {
        return elips_fail('Kelas ini tidak dapat dijadwalkan.');
    }

    $dates = [];
    if (isset($p['dates']) && is_array($p['dates'])) {
        foreach ($p['dates'] as $value) {
            $value = trim((string) $value);
            if ($value === '') continue;
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)
                || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                return elips_fail('Tanggal "' . $value . '" tidak valid.');
            }
            $dates[$value] = true;
        }
        $dates = array_keys($dates);
        sort($dates);
    } else {
        $startDate = trim((string) ($p['startDate'] ?? ''));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $startDate, $matches)
            || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return elips_fail('Tanggal awal jadwal tidak valid.');
        }
        $weekdays = [];
        foreach ((array) ($p['weekdays'] ?? []) as $weekday) {
            $weekday = (int) $weekday;
            if ($weekday >= 1 && $weekday <= 7) $weekdays[$weekday] = true;
        }
        if (!$weekdays) {
            return elips_fail('Pilih minimal satu hari dalam seminggu.');
        }
        $occurrences = max(1, min(52, (int) ($p['occurrences'] ?? count($weekdays))));
        $cursor = new DateTimeImmutable($startDate, new DateTimeZone('UTC'));
        $guard = 0;
        while (count($dates) < $occurrences && $guard < 366) {
            $isoDay = (int) $cursor->format('N');
            if (isset($weekdays[$isoDay])) {
                $dates[] = $cursor->format('Y-m-d');
            }
            $cursor = $cursor->modify('+1 day');
            $guard++;
        }
        if (!$dates) {
            return elips_fail('Tidak ada tanggal yang cocok dengan pola jadwal.');
        }
    }

    if (!$dates) return elips_fail('Tidak ada tanggal sesi yang dipilih.');
    if (count($dates) > 52) return elips_fail('Maksimal 52 sesi dapat dibuat sekaligus.');

    $db = elips_db();
    $db->beginTransaction();
    try {
        $sessionNumber = elips_session_next_number($classId);
        $created = [];
        foreach ($dates as $date) {
            $input = $p;
            $input['sessionDate'] = $date;
            unset($input['dates'], $input['startDate'], $input['weekdays'], $input['occurrences'], $input['sessionNumber']);
            $created[] = elips_session_store($class, $input, $actor, $sessionNumber);
            $sessionNumber++;
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $sessions = [];
    foreach ($created as $id) {
        $session = elips_session_public($id);
        if ($session) $sessions[] = $session;
    }

    return elips_ok(count($sessions) . ' sesi pertemuan berhasil dibuat.', ['sessions' => $sessions]);
}

function elips_action_session_update(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $session = elips_session_row($id);
    if (!$session) return elips_fail('Sesi tidak ditemukan.');
    if (!elips_can_manage_session($session, $actor)) {
        return elips_fail('Anda tidak berhak memperbarui sesi ini.');
    }
    $class = elips_class_row((int) $session['class_id']);
    if (!$class) return elips_fail('Kelas sesi tidak ditemukan.');

    $input = isset($p['data']) && is_array($p['data']) ? $p['data'] : $p;
    $data = array_merge([
        'title' => $session['title'],
        'moduleId' => $session['module_id'] === null ? 0 : (int) $session['module_id'],
        'sessionDate' => elips_date_only($session['session_date']),
        'startTime' => elips_time_short($session['start_time']),
        'endTime' => elips_time_short($session['end_time']),
        'location' => $session['location'],
        'room' => $session['room'],
        'instructorId' => $session['instructor_id'] === null ? (int) ($class['instructor_id'] ?? 0) : (int) $session['instructor_id'],
        'plannedJp' => (float) $session['planned_jp'],
        'actualJp' => (float) $session['actual_jp'],
        'status' => $session['status'],
        'notes' => $session['notes'],
    ], $input);
    unset($data['id'], $data['classId'], $data['data']);

    $sessionNumber = (int) $session['session_number'];
    $requestedNumber = (int) ($input['sessionNumber'] ?? $sessionNumber);
    if ($requestedNumber > 0 && $requestedNumber !== $sessionNumber) {
        $stmt = elips_db()->prepare('SELECT id FROM class_sessions WHERE class_id = ? AND session_number = ? AND id <> ? LIMIT 1');
        $stmt->execute([(int) $session['class_id'], $requestedNumber, $id]);
        if ($stmt->fetch()) {
            return elips_fail('Nomor pertemuan ' . $requestedNumber . ' sudah digunakan pada kelas ini.');
        }
        $sessionNumber = $requestedNumber;
    }

    $values = elips_session_normalize($class, $data, $sessionNumber);
    $stmt = elips_db()->prepare(
        'UPDATE class_sessions
         SET session_number = ?, title = ?, module_id = ?, session_date = ?, start_time = ?, end_time = ?,
             location = ?, room = ?, instructor_id = ?, planned_jp = ?, actual_jp = ?, status = ?,
             notes = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([
        $sessionNumber,
        $values['title'],
        $values['moduleId'],
        $values['sessionDate'],
        $values['startTime'],
        $values['endTime'],
        $values['location'],
        $values['room'],
        $values['instructorId'],
        $values['plannedJp'],
        $values['actualJp'],
        $values['status'],
        $values['notes'],
        (int) $actor['id'],
        $id,
    ]);

    elips_audit(
        'update',
        'session',
        $id,
        $values['title'],
        'Memperbarui sesi pertemuan ' . $sessionNumber . ' kelas "' . $class['name'] . '" pada '
            . $values['sessionDate'] . ' (' . $values['status'] . ').',
        $actor
    );

    return elips_ok('Sesi pertemuan berhasil diperbarui.', elips_session_public($id));
}

function elips_action_session_status(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $status = (string) ($p['status'] ?? '');
    $session = elips_session_row($id);
    if (!$session) return elips_fail('Sesi tidak ditemukan.');
    if (!elips_can_manage_session($session, $actor)) {
        return elips_fail('Anda tidak berhak mengubah status sesi ini.');
    }
    if (!in_array($status, elips_session_statuses(), true)) {
        return elips_fail('Status sesi tidak valid.');
    }

    $actualJp = array_key_exists('actualJp', $p)
        ? max(0, (float) $p['actualJp'])
        : (float) $session['actual_jp'];
    if ($status === 'completed' && $actualJp <= 0) {
        $actualJp = (float) $session['planned_jp'];
    }

    $reason = trim((string) ($p['reason'] ?? ''));
    $notes = (string) $session['notes'];
    if ($reason !== '') {
        $notes = $notes === '' ? 'Alasan: ' . $reason : $notes . "\n" . 'Alasan: ' . $reason;
    }

    $stmt = elips_db()->prepare(
        'UPDATE class_sessions
         SET status = ?, actual_jp = ?, notes = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([$status, round($actualJp, 2), $notes, (int) $actor['id'], $id]);

    $class = elips_class_row((int) $session['class_id']);
    $label = ($class ? $class['name'] : 'Kelas #' . $session['class_id']) . ' — Pertemuan ' . $session['session_number'];
    elips_audit(
        'status',
        'session',
        $id,
        $label,
        'Mengubah status sesi "' . $label . '" dari ' . $session['status'] . ' menjadi ' . $status . '.'
            . ($reason !== '' ? ' Alasan: ' . $reason : ''),
        $actor
    );

    return elips_ok('Status sesi diperbarui menjadi ' . $status . '.', elips_session_public($id));
}

function elips_action_session_delete(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $session = elips_session_row($id);
    if (!$session) return elips_fail('Sesi tidak ditemukan.');
    if (!elips_can_manage_session($session, $actor)) {
        return elips_fail('Anda tidak berhak menghapus sesi ini.');
    }
    if ($session['status'] === 'completed') {
        return elips_fail('Sesi yang sudah berlangsung tidak dapat dihapus. Batalkan atau koreksi datanya.');
    }

    $stmt = elips_db()->prepare('SELECT COUNT(*) FROM attendances WHERE session_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return elips_fail('Sesi sudah memiliki presensi sehingga tidak dapat dihapus. Batalkan sesi untuk menjaga histori.');
    }

    $class = elips_class_row((int) $session['class_id']);
    $label = ($class ? $class['name'] : 'Kelas #' . $session['class_id']) . ' — Pertemuan ' . $session['session_number'];

    $stmt = elips_db()->prepare('DELETE FROM class_sessions WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'session',
        $id,
        $label,
        'Menghapus sesi "' . $label . '" yang belum memiliki presensi.',
        $actor
    );

    return elips_ok('Sesi pertemuan berhasil dihapus.');
}

function elips_action_session_detail(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $session = elips_session_row($id);
    if (!$session) return elips_fail('Sesi tidak ditemukan.');

    if ($actor['role'] === 'peserta') {
        $stmt = elips_db()->prepare(
            "SELECT id FROM enrollments WHERE class_id = ? AND student_id = ? AND status IN ('active', 'pending', 'completed') LIMIT 1"
        );
        $stmt->execute([(int) $session['class_id'], (int) $actor['id']]);
        if (!$stmt->fetch()) return elips_fail('Anda tidak terdaftar pada kelas ini.');
    } elseif (!elips_can_manage_session($session, $actor)) {
        return elips_fail('Anda tidak berhak melihat sesi ini.');
    }

    return elips_ok('Detail sesi dimuat.', elips_session_public($id));
}

function elips_attendance_lookup_enrollment(array $session, array $record): ?array
{
    $enrollmentId = (int) ($record['enrollmentId'] ?? 0);
    if ($enrollmentId > 0) {
        $enrollment = elips_enrollment_row($enrollmentId);
        if ($enrollment && (int) $enrollment['class_id'] === (int) $session['class_id']) {
            return $enrollment;
        }
        return null;
    }

    $studentId = (int) ($record['studentId'] ?? 0);
    if ($studentId <= 0) {
        return null;
    }
    $stmt = elips_db()->prepare(
        "SELECT * FROM enrollments
         WHERE class_id = ? AND student_id = ? AND status IN ('active', 'pending', 'completed')
         ORDER BY id ASC LIMIT 1"
    );
    $stmt->execute([(int) $session['class_id'], $studentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_action_attendance_save_bulk(array $p): array
{
    $actor = elips_require_login();
    $sessionId = (int) ($p['sessionId'] ?? 0);
    $session = elips_session_row($sessionId);
    if (!$session) return elips_fail('Sesi tidak ditemukan.');
    if (!elips_can_manage_session($session, $actor)) {
        return elips_fail('Anda tidak berhak mengisi presensi sesi ini.');
    }
    if (in_array($session['status'], ['cancelled', 'rescheduled', 'no_show'], true)) {
        return elips_fail('Presensi tidak dapat diisi untuk sesi berstatus ' . $session['status'] . '.');
    }

    $records = elips_payload_array($p, 'records');
    if (!$records) return elips_fail('Tidak ada data presensi yang dikirim.');

    $db = elips_db();
    $db->beginTransaction();
    $saved = 0;
    $updated = 0;
    $corrected = 0;
    try {
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            $status = (string) ($record['status'] ?? '');
            if (!in_array($status, elips_attendance_statuses(), true)) {
                throw new ElipsError('Status presensi "' . $status . '" tidak valid.');
            }
            $enrollment = elips_attendance_lookup_enrollment($session, $record);
            if (!$enrollment) {
                throw new ElipsError('Peserta pada data presensi bukan anggota kelas sesi ini.');
            }

            $note = trim((string) ($record['note'] ?? ''));
            $reason = trim((string) ($record['reason'] ?? ''));
            $stmt = $db->prepare('SELECT * FROM attendances WHERE session_id = ? AND enrollment_id = ? LIMIT 1');
            $stmt->execute([$sessionId, (int) $enrollment['id']]);
            $existing = $stmt->fetch() ?: null;

            if (!$existing) {
                $stmt = $db->prepare(
                    "INSERT INTO attendances
                     (session_id, enrollment_id, student_id, status, recorded_by, recorded_at, note, correction_reason, created_at)
                     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, '', UTC_TIMESTAMP())"
                );
                $stmt->execute([
                    $sessionId,
                    (int) $enrollment['id'],
                    (int) $enrollment['student_id'],
                    $status,
                    (int) $actor['id'],
                    $note,
                ]);
                $saved++;
                continue;
            }

            $changed = $existing['status'] !== $status;
            if ($changed) {
                if ($reason === '') {
                    $reason = 'Pembaruan presensi sesi pertemuan ' . $session['session_number'] . '.';
                }
                $stmt = $db->prepare(
                    'INSERT INTO attendance_corrections
                     (attendance_id, session_id, enrollment_id, old_status, new_status,
                      actor_id, actor_name, actor_role, reason, corrected_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                );
                $stmt->execute([
                    (int) $existing['id'],
                    $sessionId,
                    (int) $enrollment['id'],
                    $existing['status'],
                    $status,
                    (int) $actor['id'],
                    $actor['name'],
                    $actor['role'],
                    $reason,
                ]);
                $corrected++;
            }

            $stmt = $db->prepare(
                'UPDATE attendances
                 SET status = ?, note = ?, recorded_by = ?, recorded_at = UTC_TIMESTAMP(),
                     corrected_at = ?, correction_reason = ?, updated_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $stmt->execute([
                $status,
                $note,
                (int) $actor['id'],
                $changed ? gmdate('Y-m-d H:i:s') : $existing['corrected_at'],
                $changed ? $reason : $existing['correction_reason'],
                (int) $existing['id'],
            ]);
            $updated++;
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $class = elips_class_row((int) $session['class_id']);
    elips_audit(
        'attendance',
        'session',
        $sessionId,
        ($class ? $class['name'] : 'Kelas #' . $session['class_id']) . ' — Pertemuan ' . $session['session_number'],
        'Menyimpan presensi sesi pertemuan ' . $session['session_number'] . ': '
            . $saved . ' baru, ' . $updated . ' diperbarui, ' . $corrected . ' dikoreksi.',
        $actor
    );

    return elips_ok('Presensi berhasil disimpan.', [
        'saved' => $saved,
        'updated' => $updated,
        'corrected' => $corrected,
        'session' => elips_session_public($sessionId),
    ]);
}

function elips_action_attendance_correct(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $status = (string) ($p['status'] ?? '');
    $reason = trim((string) ($p['reason'] ?? ''));

    $attendance = elips_attendance_row($id);
    if (!$attendance) return elips_fail('Presensi tidak ditemukan.');
    $session = elips_session_row((int) $attendance['session_id']);
    if (!$session) return elips_fail('Sesi presensi tidak ditemukan.');
    if (!elips_can_manage_session($session, $actor)) {
        return elips_fail('Anda tidak berhak mengoreksi presensi sesi ini.');
    }
    if (!in_array($status, elips_attendance_statuses(), true)) {
        return elips_fail('Status presensi tidak valid.');
    }
    if ($reason === '') return elips_fail('Alasan koreksi wajib diisi.');
    if ($attendance['status'] === $status) {
        return elips_fail('Status baru sama dengan status presensi saat ini.');
    }

    $db = elips_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO attendance_corrections
             (attendance_id, session_id, enrollment_id, old_status, new_status,
              actor_id, actor_name, actor_role, reason, corrected_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $id,
            (int) $attendance['session_id'],
            (int) $attendance['enrollment_id'],
            $attendance['status'],
            $status,
            (int) $actor['id'],
            $actor['name'],
            $actor['role'],
            $reason,
        ]);
        $correctionId = (int) $db->lastInsertId();

        $stmt = $db->prepare(
            'UPDATE attendances
             SET status = ?, corrected_at = UTC_TIMESTAMP(), correction_reason = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );
        $stmt->execute([$status, $reason, $id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $student = elips_user_row((int) $attendance['student_id']);
    $class = elips_class_row((int) $session['class_id']);
    $label = ($student ? $student['name'] : 'Peserta #' . $attendance['student_id'])
        . ' — ' . ($class ? $class['name'] : 'Kelas #' . $session['class_id'])
        . ' / Pertemuan ' . $session['session_number'];

    elips_audit(
        'correct',
        'attendance',
        $id,
        $label,
        'Mengoreksi presensi "' . $label . '" dari ' . $attendance['status'] . ' menjadi ' . $status . '. Alasan: ' . $reason,
        $actor
    );

    return elips_ok('Koreksi presensi berhasil disimpan.', [
        'attendance' => elips_attendance_public($id),
        'correction' => elips_attendance_correction_public($correctionId),
        'session' => elips_session_public((int) $attendance['session_id']),
    ]);
}

function elips_action_attendance_recap(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if ($actor['role'] !== 'admin'
        && !($actor['role'] === 'instruktur'
            && $class['instructor_id'] !== null
            && (int) $class['instructor_id'] === (int) $actor['id'])) {
        return elips_fail('Anda tidak berhak melihat rekap kelas ini.');
    }

    $recap = elips_attendance_recap($classId);
    if (!$recap) return elips_fail('Rekap kehadiran tidak tersedia.');

    return elips_ok('Rekap kehadiran dimuat.', $recap);
}

function elips_action_attendance_my_recap(array $p): array
{
    $actor = elips_require_login();
    if ($actor['role'] !== 'peserta') {
        return elips_fail('Rekap ini hanya tersedia untuk akun peserta.');
    }

    $db = elips_db();
    $classId = (int) ($p['classId'] ?? 0);
    $sql = "SELECT DISTINCT class_id FROM enrollments
            WHERE student_id = ? AND class_id IS NOT NULL AND status IN ('active', 'pending', 'completed')";
    $params = [(int) $actor['id']];
    if ($classId > 0) {
        $sql .= ' AND class_id = ?';
        $params[] = $classId;
    }
    $sql .= ' ORDER BY class_id ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $classIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $classes = [];
    foreach ($classIds as $id) {
        $recap = elips_attendance_recap($id, (int) $actor['id']);
        if ($recap) $classes[] = $recap;
    }

    return elips_ok('Rekap kehadiran Anda dimuat.', ['classes' => $classes]);
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

    $versionId = (int) ($p['curriculumVersionId'] ?? 0);
    if ($versionId > 0) {
        $version = elips_curriculum_version_row($versionId);
        if (!$version || (int) $version['course_id'] !== $courseId) {
            return elips_fail('Versi kurikulum tidak valid untuk kursus ini.');
        }
    } else {
        $version = elips_ensure_curriculum_version($courseId, (int) $actor['id']);
        $versionId = (int) $version['id'];
    }
    if (!elips_curriculum_is_editable($version)) {
        return elips_fail('Versi kurikulum ini sudah dikunci atau dipensiunkan. Buat versi baru untuk mengubah materi.');
    }

    $db = elips_db();
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM modules WHERE curriculum_version_id = ?');
    $stmt->execute([$versionId]);
    $nextOrder = (int) $stmt->fetchColumn() + 1;

    $stmt = $db->prepare(
        'INSERT INTO modules (course_id, curriculum_version_id, instructor_id, title, description, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $courseId,
        $versionId,
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
    $version = elips_curriculum_version_row((int) $module['curriculum_version_id']);
    if (!$version || !elips_curriculum_is_editable($version)) {
        return elips_fail('Kurikulum kelas ini sudah dikunci. Buat versi baru untuk melakukan perubahan.');
    }

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
    $version = elips_curriculum_version_row((int) $module['curriculum_version_id']);
    if (!$version || !elips_curriculum_is_editable($version)) {
        return elips_fail('Kurikulum kelas ini sudah dikunci. Buat versi baru untuk melakukan perubahan.');
    }

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
    $version = elips_curriculum_version_row((int) $module['curriculum_version_id']);
    if (!$version || !elips_curriculum_is_editable($version)) {
        return elips_fail('Kurikulum kelas ini sudah dikunci. Buat versi baru untuk melakukan perubahan.');
    }

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
        'INSERT INTO lessons (module_id, course_id, curriculum_version_id, instructor_id, title, type, content, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $moduleId,
        (int) $module['course_id'],
        (int) $module['curriculum_version_id'],
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
    $version = elips_curriculum_version_row((int) $lesson['curriculum_version_id']);
    if (!$version || !elips_curriculum_is_editable($version)) {
        return elips_fail('Kurikulum kelas ini sudah dikunci. Buat versi baru untuk melakukan perubahan.');
    }

    $targetModuleId = array_key_exists('moduleId', $data) ? (int) $data['moduleId'] : (int) $lesson['module_id'];
    $stmt = elips_db()->prepare('SELECT * FROM modules WHERE id = ? LIMIT 1');
    $stmt->execute([$targetModuleId]);
    $targetModule = $stmt->fetch();
    if (!$targetModule
        || (int) $targetModule['course_id'] !== (int) $lesson['course_id']
        || (int) $targetModule['curriculum_version_id'] !== (int) $lesson['curriculum_version_id']) {
        return elips_fail('Modul tujuan tidak valid untuk versi kurikulum materi ini.');
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
    $version = elips_curriculum_version_row((int) $lesson['curriculum_version_id']);
    if (!$version || !elips_curriculum_is_editable($version)) {
        return elips_fail('Kurikulum kelas ini sudah dikunci. Buat versi baru untuk melakukan perubahan.');
    }

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

// ---------- Materi kelas (Phase 4) ----------

function elips_class_lesson_row(int $classId, int $lessonId): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT l.* FROM lessons l
         JOIN classes cl ON cl.id = ?
            AND cl.course_id = l.course_id
            AND cl.curriculum_version_id = l.curriculum_version_id
         WHERE l.id = ? LIMIT 1'
    );
    $stmt->execute([$classId, $lessonId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_class_session_row(int $classId, int $sessionId): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM class_sessions WHERE id = ? AND class_id = ? LIMIT 1');
    $stmt->execute([$sessionId, $classId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_material_enrollment_for(int $classId, int $studentId): ?array
{
    $stmt = elips_db()->prepare(
        "SELECT * FROM enrollments
         WHERE class_id = ? AND student_id = ? AND status IN ('active', 'completed')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$classId, $studentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_material_enrollment_by_id(int $classId, int $enrollmentId): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM enrollments WHERE id = ? AND class_id = ? LIMIT 1');
    $stmt->execute([$enrollmentId, $classId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_material_valid_access_level(string $level): bool
{
    return in_array($level, ['required', 'optional', 'reference'], true);
}

function elips_material_valid_type(string $type): bool
{
    return in_array($type, ['text', 'document', 'video', 'link', 'file'], true);
}

function elips_material_normalize_file_size($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $size = (int) $value;
    return $size >= 0 ? $size : null;
}

function elips_material_classroom_payload(array $class, ?array $enrollment, bool $preview): array
{
    $course = elips_course_row((int) $class['course_id']);
    $state = elips_class_material_state($class, $enrollment, $preview);
    return array_merge([
        'class' => [
            'id' => (int) $class['id'],
            'code' => $class['code'],
            'name' => $class['name'],
            'courseId' => (int) $class['course_id'],
            'courseTitle' => $course ? $course['title'] : '',
            'curriculumVersionId' => (int) $class['curriculum_version_id'],
            'instructorId' => $class['instructor_id'] === null ? null : (int) $class['instructor_id'],
            'status' => $class['status'],
            'periodStart' => elips_date_only($class['period_start']),
            'periodEnd' => elips_date_only($class['period_end']),
        ],
        'enrollment' => $enrollment ? elips_enrollment_public((int) $enrollment['id']) : null,
        'preview' => $preview,
    ], $state);
}

function elips_action_material_settings(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');

    if (!elips_can_manage_class($class, $actor)) {
        if ($actor['role'] !== 'peserta'
            || !elips_material_enrollment_for($classId, (int) $actor['id'])) {
            return elips_fail('Anda tidak berhak melihat pengaturan materi kelas ini.');
        }
    }

    return elips_ok('Pengaturan akses materi dimuat.', elips_class_material_settings_public($classId));
}

function elips_action_material_update_settings(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak mengatur akses materi kelas ini.');
    }

    $data = elips_payload_array($p, 'data');
    $current = elips_class_material_settings_public($classId);
    $mode = trim((string) ($data['accessMode'] ?? $current['accessMode']));
    if (!in_array($mode, ['open', 'sequential', 'scheduled', 'conditional'], true)) {
        return elips_fail('Mode akses materi tidak valid.');
    }
    $approval = array_key_exists('requireInstructorApproval', $data)
        ? (bool) $data['requireInstructorApproval']
        : $current['requireInstructorApproval'];
    $notes = array_key_exists('notes', $data)
        ? trim((string) $data['notes'])
        : $current['notes'];
    $reason = trim((string) ($data['reason'] ?? ($p['reason'] ?? '')));

    $changed = $mode !== $current['accessMode']
        || $approval !== $current['requireInstructorApproval']
        || $notes !== $current['notes'];
    if ($actor['role'] === 'admin' && $current['id'] !== null && $changed && $reason === '') {
        return elips_fail('Alasan override Admin wajib diisi.');
    }

    $db = elips_db();
    if ($current['id'] === null) {
        $stmt = $db->prepare(
            'INSERT INTO class_material_settings
             (class_id, access_mode, require_instructor_approval, notes, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $classId,
            $mode,
            $approval ? 1 : 0,
            $notes,
            (int) $actor['id'],
        ]);
    } else {
        $stmt = $db->prepare(
            'UPDATE class_material_settings
             SET access_mode = ?, require_instructor_approval = ?, notes = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()
             WHERE class_id = ?'
        );
        $stmt->execute([
            $mode,
            $approval ? 1 : 0,
            $notes,
            (int) $actor['id'],
            $classId,
        ]);
    }

    $detail = 'Mengubah pengaturan akses materi kelas "' . $class['code'] . '" dari '
        . $current['accessMode'] . ' menjadi ' . $mode
        . '; persetujuan instruktur: ' . ($approval ? 'aktif' : 'nonaktif')
        . ($reason !== '' ? '; alasan: ' . $reason : '');
    elips_audit('update', 'material_settings', $classId, $class['code'], $detail . '.', $actor);

    return elips_ok('Pengaturan akses materi diperbarui.', elips_class_material_settings_public($classId));
}

function elips_action_material_create(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak menambahkan materi pada kelas ini.');
    }

    $data = elips_payload_array($p, 'data');
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') return elips_fail('Judul materi wajib diisi.');

    $type = trim((string) ($data['type'] ?? 'text'));
    if (!elips_material_valid_type($type)) return elips_fail('Jenis materi tidak valid.');

    $accessLevel = trim((string) ($data['accessLevel'] ?? 'optional'));
    if (!elips_material_valid_access_level($accessLevel)) {
        return elips_fail('Status materi tidak valid.');
    }

    $status = trim((string) ($data['status'] ?? 'active'));
    if (!in_array($status, ['active', 'hidden'], true)) {
        return elips_fail('Status publikasi materi tidak valid.');
    }

    $lessonId = null;
    if (isset($data['lessonId']) && (int) $data['lessonId'] > 0) {
        $lessonId = (int) $data['lessonId'];
        if (!elips_class_lesson_row($classId, $lessonId)) {
            return elips_fail('Materi kurikulum tidak valid untuk kelas ini.');
        }
    }

    $sessionId = null;
    if (isset($data['sessionId']) && (int) $data['sessionId'] > 0) {
        $sessionId = (int) $data['sessionId'];
        if (!elips_class_session_row($classId, $sessionId)) {
            return elips_fail('Sesi kelas tidak valid untuk kelas ini.');
        }
    }

    $availableFrom = elips_normalize_date($data['availableFrom'] ?? null);
    if (!empty($data['availableFrom']) && $availableFrom === null) {
        return elips_fail('Tanggal ketersediaan materi tidak valid.');
    }

    $content = (string) ($data['content'] ?? '');
    $content = $type === 'text' ? elips_sanitize_html($content) : trim($content);

    $order = (int) ($data['order'] ?? 0);
    $db = elips_db();
    if ($order <= 0) {
        $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM class_materials WHERE class_id = ?');
        $stmt->execute([$classId]);
        $order = (int) $stmt->fetchColumn() + 1;
    }

    $stmt = $db->prepare(
        'INSERT INTO class_materials
         (class_id, lesson_id, session_id, title, description, type, content, file_name, file_path, file_size,
          mime_type, access_level, available_from, status, reason, sort_order, created_by, updated_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'\', ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $classId,
        $lessonId,
        $sessionId,
        $title,
        trim((string) ($data['description'] ?? '')),
        $type,
        $content,
        trim((string) ($data['fileName'] ?? '')),
        elips_material_normalize_file_size($data['fileSize'] ?? null),
        trim((string) ($data['mimeType'] ?? '')),
        $accessLevel,
        $availableFrom,
        $status,
        trim((string) ($data['reason'] ?? '')),
        $order,
        (int) $actor['id'],
        (int) $actor['id'],
    ]);
    $id = (int) $db->lastInsertId();

    elips_audit(
        'create',
        'class_material',
        $id,
        $title,
        'Menambahkan materi tambahan "' . $title . '" pada kelas "' . $class['code'] . '".',
        $actor
    );

    $row = elips_class_material_row($id);
    return elips_ok('Materi tambahan berhasil ditambahkan.', $row ? elips_class_material_public($row) : null);
}

function elips_action_material_update(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $material = elips_class_material_row($id);
    if (!$material) return elips_fail('Materi tidak ditemukan.');

    $class = elips_class_row((int) $material['class_id']);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak mengubah materi pada kelas ini.');
    }

    $data = elips_payload_array($p, 'data');
    $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $material['title'];
    if ($title === '') return elips_fail('Judul materi wajib diisi.');

    $type = array_key_exists('type', $data) ? trim((string) $data['type']) : $material['type'];
    if (!elips_material_valid_type($type)) return elips_fail('Jenis materi tidak valid.');

    $accessLevel = array_key_exists('accessLevel', $data)
        ? trim((string) $data['accessLevel'])
        : $material['access_level'];
    if (!elips_material_valid_access_level($accessLevel)) {
        return elips_fail('Status materi tidak valid.');
    }

    $lessonId = $material['lesson_id'] === null ? null : (int) $material['lesson_id'];
    if (array_key_exists('lessonId', $data)) {
        $lessonId = (int) ($data['lessonId'] ?? 0);
        if ($lessonId <= 0) {
            $lessonId = null;
        } elseif (!elips_class_lesson_row((int) $class['id'], $lessonId)) {
            return elips_fail('Materi kurikulum tidak valid untuk kelas ini.');
        }
    }

    $sessionId = $material['session_id'] === null ? null : (int) $material['session_id'];
    if (array_key_exists('sessionId', $data)) {
        $sessionId = (int) ($data['sessionId'] ?? 0);
        if ($sessionId <= 0) {
            $sessionId = null;
        } elseif (!elips_class_session_row((int) $class['id'], $sessionId)) {
            return elips_fail('Sesi kelas tidak valid untuk kelas ini.');
        }
    }

    $availableFrom = elips_date_only($material['available_from']);
    if (array_key_exists('availableFrom', $data)) {
        $availableFrom = elips_normalize_date($data['availableFrom']);
        if (!empty($data['availableFrom']) && $availableFrom === null) {
            return elips_fail('Tanggal ketersediaan materi tidak valid.');
        }
    }

    $content = array_key_exists('content', $data) ? (string) $data['content'] : $material['content'];
    $content = $type === 'text' ? elips_sanitize_html($content) : trim($content);

    $order = array_key_exists('order', $data) ? (int) $data['order'] : (int) $material['sort_order'];
    if ($order <= 0) $order = 1;

    $stmt = elips_db()->prepare(
        'UPDATE class_materials
         SET lesson_id = ?, session_id = ?, title = ?, description = ?, type = ?, content = ?,
             file_name = ?, file_size = ?, mime_type = ?, access_level = ?, available_from = ?,
             reason = ?, sort_order = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([
        $lessonId,
        $sessionId,
        $title,
        array_key_exists('description', $data) ? trim((string) $data['description']) : $material['description'],
        $type,
        $content,
        array_key_exists('fileName', $data) ? trim((string) $data['fileName']) : $material['file_name'],
        array_key_exists('fileSize', $data)
            ? elips_material_normalize_file_size($data['fileSize'])
            : elips_material_normalize_file_size($material['file_size']),
        array_key_exists('mimeType', $data) ? trim((string) $data['mimeType']) : $material['mime_type'],
        $accessLevel,
        $availableFrom,
        array_key_exists('reason', $data) ? trim((string) $data['reason']) : $material['reason'],
        $order,
        (int) $actor['id'],
        $id,
    ]);

    elips_audit(
        'update',
        'class_material',
        $id,
        $title,
        'Memperbarui materi tambahan "' . $title . '" pada kelas "' . $class['code'] . '".',
        $actor
    );

    $row = elips_class_material_row($id);
    return elips_ok('Materi tambahan berhasil diperbarui.', $row ? elips_class_material_public($row) : null);
}

function elips_action_material_status(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $material = elips_class_material_row($id);
    if (!$material) return elips_fail('Materi tidak ditemukan.');

    $class = elips_class_row((int) $material['class_id']);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak mengubah status materi ini.');
    }

    $data = elips_payload_array($p, 'data');
    $status = trim((string) ($data['status'] ?? ($p['status'] ?? '')));
    if (!in_array($status, ['active', 'hidden'], true)) {
        return elips_fail('Status materi tidak valid.');
    }
    $reason = trim((string) ($data['reason'] ?? ($p['reason'] ?? '')));
    if ($reason === '') return elips_fail('Alasan perubahan status materi wajib diisi.');

    $stmt = elips_db()->prepare(
        'UPDATE class_materials SET status = ?, reason = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([$status, $reason, (int) $actor['id'], $id]);

    elips_audit(
        'status',
        'class_material',
        $id,
        $material['title'],
        'Mengubah status materi "' . $material['title'] . '" pada kelas "' . $class['code'] . '" dari '
            . $material['status'] . ' menjadi ' . $status . '; alasan: ' . $reason . '.',
        $actor
    );

    $row = elips_class_material_row($id);
    return elips_ok('Status materi diperbarui.', $row ? elips_class_material_public($row) : null);
}

function elips_action_material_delete(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $material = elips_class_material_row($id);
    if (!$material) return elips_fail('Materi tidak ditemukan.');

    $class = elips_class_row((int) $material['class_id']);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak menghapus materi ini.');
    }

    $stmt = elips_db()->prepare('SELECT COUNT(*) FROM class_material_progress WHERE material_id = ?');
    $stmt->execute([$id]);
    $progressCount = (int) $stmt->fetchColumn();

    $stmt = elips_db()->prepare('DELETE FROM class_materials WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'class_material',
        $id,
        $material['title'],
        'Menghapus materi tambahan "' . $material['title'] . '" dari kelas "' . $class['code']
            . '" beserta ' . $progressCount . ' catatan progres.',
        $actor
    );

    return elips_ok('Materi tambahan dihapus.', [
        'id' => $id,
        'removedProgress' => $progressCount,
    ]);
}

function elips_action_material_classroom(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');

    $preview = elips_can_manage_class($class, $actor);
    $enrollment = null;

    if ($preview) {
        $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
        if ($enrollmentId > 0) {
            $enrollment = elips_material_enrollment_by_id($classId, $enrollmentId);
            if (!$enrollment) return elips_fail('Peserta tidak terdaftar pada kelas ini.');
        }
    } else {
        if ($actor['role'] !== 'peserta') {
            return elips_fail('Anda tidak berhak membuka materi kelas ini.');
        }
        $course = elips_course_row((int) $class['course_id']);
        if (!$course || $course['status'] !== 'published') {
            return elips_fail('Kursus belum dipublikasikan.');
        }
        $enrollment = elips_material_enrollment_for($classId, (int) $actor['id']);
        if (!$enrollment) return elips_fail('Anda tidak terdaftar pada kelas ini.');
    }

    return elips_ok('Ruang belajar materi dimuat.', elips_material_classroom_payload($class, $enrollment, $preview));
}

function elips_action_material_progress(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');

    $lessonId = (int) ($p['lessonId'] ?? 0);
    $materialId = (int) ($p['materialId'] ?? 0);
    if (($lessonId > 0) === ($materialId > 0)) {
        return elips_fail('Pilih satu materi atau pelajaran yang akan ditandai.');
    }

    $completed = array_key_exists('completed', $p) ? (bool) $p['completed'] : true;
    $staffMode = elips_can_manage_class($class, $actor);
    $enrollment = null;

    if ($staffMode) {
        $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
        if ($enrollmentId <= 0 && !empty($p['studentId'])) {
            $enrollment = elips_material_enrollment_for($classId, (int) $p['studentId']);
        }
        if ($enrollmentId > 0) {
            $enrollment = elips_material_enrollment_by_id($classId, $enrollmentId);
        }
        if (!$enrollment) return elips_fail('Pilih peserta yang terdaftar pada kelas ini.');
        if (!in_array($enrollment['status'], ['active', 'completed', 'pending'], true)) {
            return elips_fail('Status penugasan peserta tidak memungkinkan pencatatan progres.');
        }
    } else {
        if ($actor['role'] !== 'peserta') {
            return elips_fail('Anda tidak berhak mencatat progres materi ini.');
        }
        $course = elips_course_row((int) $class['course_id']);
        if (!$course || $course['status'] !== 'published') {
            return elips_fail('Kursus belum dipublikasikan.');
        }
        $enrollment = elips_material_enrollment_for($classId, (int) $actor['id']);
        if (!$enrollment) return elips_fail('Anda tidak terdaftar pada kelas ini.');

        $settings = elips_class_material_settings_public($classId);
        if ($settings['requireInstructorApproval']) {
            return elips_fail('Penyelesaian materi perlu dikonfirmasi instruktur.');
        }
    }

    $db = elips_db();
    $enrollmentId = (int) $enrollment['id'];
    $studentId = (int) $enrollment['student_id'];
    $state = elips_class_material_state($class, $enrollment, $staffMode);
    $label = '';
    $kind = '';

    if ($lessonId > 0) {
        $item = null;
        foreach ($state['lessons'] as $lesson) {
            if ((int) $lesson['id'] === $lessonId) {
                $item = $lesson;
                break;
            }
        }
        if (!$item) return elips_fail('Pelajaran tidak ditemukan pada kelas ini.');
        if (!$staffMode && $completed && $item['locked']) {
            return elips_fail($item['reason'] !== '' ? $item['reason'] : 'Materi masih terkunci.');
        }
        $kind = 'lesson';
        $label = $item['title'];
    } else {
        $item = null;
        foreach ($state['materials'] as $material) {
            if ((int) $material['id'] === $materialId) {
                $item = $material;
                break;
            }
        }
        if (!$item) return elips_fail('Materi tambahan tidak ditemukan pada kelas ini.');
        if (!$staffMode && $completed && $item['locked']) {
            return elips_fail($item['reason'] !== '' ? $item['reason'] : 'Materi masih terkunci.');
        }
        $kind = 'material';
        $label = $item['title'];
    }

    if ($completed) {
        if ($lessonId > 0) {
            $stmt = $db->prepare(
                'INSERT IGNORE INTO class_lesson_progress
                 (class_id, enrollment_id, student_id, lesson_id, completed_by, completed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmt->execute([$classId, $enrollmentId, $studentId, $lessonId, (int) $actor['id']]);
        } else {
            $stmt = $db->prepare(
                'INSERT IGNORE INTO class_material_progress
                 (class_id, enrollment_id, student_id, material_id, completed_by, completed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmt->execute([$classId, $enrollmentId, $studentId, $materialId, (int) $actor['id']]);
        }
    } else {
        if ($lessonId > 0) {
            $stmt = $db->prepare('DELETE FROM class_lesson_progress WHERE enrollment_id = ? AND lesson_id = ?');
            $stmt->execute([$enrollmentId, $lessonId]);
        } else {
            $stmt = $db->prepare('DELETE FROM class_material_progress WHERE enrollment_id = ? AND material_id = ?');
            $stmt->execute([$enrollmentId, $materialId]);
        }
    }

    if ($staffMode) {
        elips_audit(
            $completed ? 'confirm' : 'unconfirm',
            'class_' . $kind . '_progress',
            $lessonId > 0 ? $lessonId : $materialId,
            $label,
            ($completed ? 'Mengonfirmasi' : 'Membatalkan') . ' penyelesaian ' . $kind . ' "' . $label
                . '" untuk peserta #' . $studentId . ' pada kelas "' . $class['code'] . '".',
            $actor
        );
    }

    return elips_ok(
        $completed ? 'Progres materi ditandai selesai.' : 'Tanda selesai materi dibatalkan.',
        elips_material_classroom_payload($class, $enrollment, $staffMode)
    );
}

// ---------- Tugas, submission, dan ujian (Phase 5) ----------

function elips_task_classroom_context(array $p, array $actor): array
{
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return ['error' => 'Kelas tidak ditemukan.'];

    $preview = elips_can_manage_class($class, $actor);
    $enrollment = null;
    if ($preview) {
        $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
        if ($enrollmentId > 0) {
            $enrollment = elips_class_enrollment_by_id($classId, $enrollmentId);
            if (!$enrollment) return ['error' => 'Peserta tidak terdaftar pada kelas ini.'];
        }
    } else {
        if ($actor['role'] !== 'peserta') {
            return ['error' => 'Anda tidak berhak membuka ruang tugas kelas ini.'];
        }
        $course = elips_course_row((int) $class['course_id']);
        if (!$course || $course['status'] !== 'published') {
            return ['error' => 'Kursus belum dipublikasikan.'];
        }
        $enrollment = elips_class_enrollment_for($classId, (int) $actor['id']);
        if (!$enrollment) return ['error' => 'Anda tidak terdaftar pada kelas ini.'];
    }

    return [
        'class' => $class,
        'enrollment' => $enrollment,
        'preview' => $preview,
    ];
}

function elips_task_assignment_context(int $id, array $actor, bool $staffOnly = false): array
{
    $assignment = elips_assignment_row($id);
    if (!$assignment) return ['error' => 'Tugas tidak ditemukan.'];
    $class = elips_class_row((int) $assignment['class_id']);
    if (!$class) return ['error' => 'Kelas tidak ditemukan.'];

    $staff = elips_can_manage_class($class, $actor);
    if ($staff) {
        return [
            'assignment' => $assignment,
            'class' => $class,
            'staff' => true,
            'enrollment' => null,
        ];
    }
    if ($staffOnly) return ['error' => 'Anda tidak berhak mengelola tugas pada kelas ini.'];
    if ($actor['role'] !== 'peserta') {
        return ['error' => 'Anda tidak berhak membuka tugas kelas ini.'];
    }
    $course = elips_course_row((int) $class['course_id']);
    if (!$course || $course['status'] !== 'published') {
        return ['error' => 'Kursus belum dipublikasikan.'];
    }
    $enrollment = elips_class_enrollment_for((int) $class['id'], (int) $actor['id']);
    if (!$enrollment) return ['error' => 'Anda tidak terdaftar pada kelas ini.'];
    if (!in_array($assignment['status'], ['published', 'closed'], true)) {
        return ['error' => 'Tugas belum dipublikasikan.'];
    }
    return [
        'assignment' => $assignment,
        'class' => $class,
        'staff' => false,
        'enrollment' => $enrollment,
    ];
}

function elips_task_exam_context(int $id, array $actor, bool $staffOnly = false): array
{
    $exam = elips_exam_row($id);
    if (!$exam) return ['error' => 'Ujian tidak ditemukan.'];
    $class = elips_class_row((int) $exam['class_id']);
    if (!$class) return ['error' => 'Kelas tidak ditemukan.'];

    $staff = elips_can_manage_class($class, $actor);
    if ($staff) {
        return [
            'exam' => $exam,
            'class' => $class,
            'staff' => true,
            'enrollment' => null,
        ];
    }
    if ($staffOnly) return ['error' => 'Anda tidak berhak mengelola ujian pada kelas ini.'];
    if ($actor['role'] !== 'peserta') {
        return ['error' => 'Anda tidak berhak membuka ujian kelas ini.'];
    }
    $course = elips_course_row((int) $class['course_id']);
    if (!$course || $course['status'] !== 'published') {
        return ['error' => 'Kursus belum dipublikasikan.'];
    }
    $enrollment = elips_class_enrollment_for((int) $class['id'], (int) $actor['id']);
    if (!$enrollment) return ['error' => 'Anda tidak terdaftar pada kelas ini.'];
    if (!in_array($exam['status'], ['published', 'closed'], true)) {
        return ['error' => 'Ujian belum dipublikasikan.'];
    }
    return [
        'exam' => $exam,
        'class' => $class,
        'staff' => false,
        'enrollment' => $enrollment,
    ];
}

function elips_task_curriculum_refs(array $class, int $moduleId, int $lessonId): array
{
    $courseId = (int) $class['course_id'];
    $versionId = (int) $class['curriculum_version_id'];
    $result = ['moduleId' => $moduleId > 0 ? $moduleId : null, 'lessonId' => $lessonId > 0 ? $lessonId : null];

    if ($lessonId > 0) {
        $stmt = elips_db()->prepare(
            'SELECT * FROM lessons WHERE id = ? AND course_id = ? AND curriculum_version_id = ? LIMIT 1'
        );
        $stmt->execute([$lessonId, $courseId, $versionId]);
        $lesson = $stmt->fetch();
        if (!$lesson) {
            return ['error' => 'Materi kurikulum tidak valid untuk kelas ini.'];
        }
        if ($moduleId > 0 && (int) $lesson['module_id'] !== $moduleId) {
            return ['error' => 'Materi tidak berada pada modul yang dipilih.'];
        }
        $result['moduleId'] = (int) $lesson['module_id'];
        $result['lessonId'] = (int) $lesson['id'];
        return $result;
    }

    if ($moduleId > 0) {
        $stmt = elips_db()->prepare(
            'SELECT * FROM modules WHERE id = ? AND course_id = ? AND curriculum_version_id = ? LIMIT 1'
        );
        $stmt->execute([$moduleId, $courseId, $versionId]);
        if (!$stmt->fetch()) {
            return ['error' => 'Modul kurikulum tidak valid untuk kelas ini.'];
        }
    }

    return $result;
}

function elips_task_normalize_files($value): array
{
    if (!is_array($value)) return ['files' => [], 'error' => null];
    $files = [];
    foreach ($value as $item) {
        if (!is_array($item)) continue;
        $fileName = trim((string) ($item['fileName'] ?? $item['file_name'] ?? ''));
        if ($fileName === '') continue;
        if (count($files) >= 20) {
            return ['files' => [], 'error' => 'Maksimal 20 file per pengumpulan.'];
        }
        $fileSize = $item['fileSize'] ?? $item['file_size'] ?? null;
        $fileSize = ($fileSize === null || $fileSize === '') ? null : max(0, (int) $fileSize);
        $files[] = [
            'fileName' => mb_substr($fileName, 0, 255),
            'filePath' => trim((string) ($item['filePath'] ?? $item['file_path'] ?? '')),
            'fileSize' => $fileSize,
            'mimeType' => mb_substr(trim((string) ($item['mimeType'] ?? $item['mime_type'] ?? '')), 0, 120),
        ];
    }
    return ['files' => $files, 'error' => null];
}

function elips_task_allowed_file_error(string $fileName, array $assignment): ?string
{
    $allowed = array_values(array_filter(array_map(
        'trim',
        explode(',', strtolower((string) $assignment['allowed_types']))
    )));
    if ($allowed && !in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), $allowed, true)) {
        return 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed) . '.';
    }
    return null;
}

function elips_task_save_assignment_files(int $assignmentId, array $files): void
{
    $db = elips_db();
    $stmt = $db->prepare('DELETE FROM assignment_files WHERE assignment_id = ?');
    $stmt->execute([$assignmentId]);
    $stmt = $db->prepare(
        'INSERT INTO assignment_files (assignment_id, file_name, file_path, file_size, mime_type, created_at)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    foreach ($files as $file) {
        $stmt->execute([
            $assignmentId,
            $file['fileName'],
            $file['filePath'],
            $file['fileSize'],
            $file['mimeType'],
        ]);
    }
}

function elips_task_save_rubrics(int $assignmentId, array $rubrics): ?string
{
    $normalized = [];
    foreach ($rubrics as $index => $item) {
        if (!is_array($item)) continue;
        $criterion = trim((string) ($item['criterion'] ?? ''));
        if ($criterion === '') return 'Kriteria rubrik wajib diisi.';
        $maxScore = (float) ($item['maxScore'] ?? $item['max_score'] ?? 0);
        if ($maxScore <= 0) return 'Nilai maksimum rubrik harus lebih dari 0.';
        $normalized[] = [
            'criterion' => mb_substr($criterion, 0, 200),
            'description' => trim((string) ($item['description'] ?? '')),
            'maxScore' => $maxScore,
            'order' => (int) ($item['order'] ?? $item['sort_order'] ?? ($index + 1)),
        ];
    }

    $db = elips_db();
    $stmt = $db->prepare('DELETE FROM assignment_rubrics WHERE assignment_id = ?');
    $stmt->execute([$assignmentId]);
    $stmt = $db->prepare(
        'INSERT INTO assignment_rubrics (assignment_id, criterion, description, max_score, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    foreach ($normalized as $item) {
        $stmt->execute([
            $assignmentId,
            $item['criterion'],
            $item['description'],
            $item['maxScore'],
            max(1, $item['order']),
        ]);
    }
    return null;
}

function elips_task_save_submission_files(int $submissionId, array $files): void
{
    $db = elips_db();
    $stmt = $db->prepare('DELETE FROM submission_files WHERE submission_id = ?');
    $stmt->execute([$submissionId]);
    $stmt = $db->prepare(
        'INSERT INTO submission_files (submission_id, file_name, file_path, file_size, mime_type, created_at)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    foreach ($files as $file) {
        $stmt->execute([
            $submissionId,
            $file['fileName'],
            $file['filePath'],
            $file['fileSize'],
            $file['mimeType'],
        ]);
    }
}

function elips_task_assignment_detail_payload(array $assignment, ?int $enrollmentId): array
{
    $id = (int) $assignment['id'];
    $item = elips_assignment_public($assignment, true);
    $item['files'] = elips_assignment_files_for($id, true);
    $item['rubrics'] = elips_assignment_rubrics_for($id);
    $item['stats'] = elips_assignment_stats($id);
    $item['submission'] = null;

    if ($enrollmentId) {
        $submissionRow = elips_assignment_submission_for($id, $enrollmentId);
        if ($submissionRow) {
            $submission = elips_submission_public($submissionRow, true);
            $submission['files'] = elips_submission_files_for((int) $submissionRow['id'], true);
            $submission['reviews'] = elips_submission_reviews_for((int) $submissionRow['id']);
            $item['submission'] = $submission;
        }
    }

    return $item;
}

function elips_task_exam_detail_payload(array $exam, bool $staff, ?int $enrollmentId): array
{
    $id = (int) $exam['id'];
    $item = elips_exam_public($exam, true);
    if ($staff) {
        elips_exam_ensure_roster($id, (int) $exam['class_id']);
    }
    $item['stats'] = elips_exam_stats($id);
    $item['participants'] = $staff ? elips_exam_participants_for($id) : [];
    $item['results'] = $staff ? elips_exam_results_for($id) : [];
    $item['result'] = null;
    if ($enrollmentId) {
        $resultRow = elips_exam_result_for($id, $enrollmentId);
        if ($resultRow) $item['result'] = elips_exam_result_public($resultRow);
    }
    return $item;
}

function elips_action_assignment_classroom(array $p): array
{
    $actor = elips_require_login();
    $context = elips_task_classroom_context($p, $actor);
    if (isset($context['error'])) return elips_fail($context['error']);
    return elips_ok(
        'Ruang tugas dan ujian dimuat.',
        elips_assignment_classroom_payload($context['class'], $context['enrollment'], $context['preview'])
    );
}

function elips_action_assignment_detail(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_assignment_context($id, $actor);
    if (isset($context['error'])) return elips_fail($context['error']);

    $enrollmentId = 0;
    if ($context['staff']) {
        $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
        if ($enrollmentId > 0
            && !elips_class_enrollment_by_id((int) $context['class']['id'], $enrollmentId)) {
            return elips_fail('Peserta tidak terdaftar pada kelas ini.');
        }
        if ($enrollmentId <= 0 && !empty($p['studentId'])) {
            $enrollment = elips_class_enrollment_for(
                (int) $context['class']['id'],
                (int) $p['studentId']
            );
            $enrollmentId = $enrollment ? (int) $enrollment['id'] : 0;
        }
    } else {
        $enrollmentId = (int) $context['enrollment']['id'];
    }

    return elips_ok(
        'Detail tugas dimuat.',
        elips_task_assignment_detail_payload($context['assignment'], $enrollmentId)
    );
}

function elips_action_assignment_create(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak menambahkan tugas pada kelas ini.');
    }

    $data = elips_payload_array($p, 'data');
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') return elips_fail('Judul tugas wajib diisi.');

    $type = trim((string) ($data['type'] ?? 'text_submission'));
    if (!elips_assignment_type_valid($type)) return elips_fail('Jenis tugas tidak valid.');

    $status = trim((string) ($data['status'] ?? 'draft'));
    if (!elips_assignment_status_valid($status)) return elips_fail('Status tugas tidak valid.');

    $refs = elips_task_curriculum_refs(
        $class,
        (int) ($data['moduleId'] ?? 0),
        (int) ($data['lessonId'] ?? 0)
    );
    if (isset($refs['error'])) return elips_fail($refs['error']);

    $openAt = elips_normalize_datetime($data['openAt'] ?? null);
    if (!empty($data['openAt']) && $openAt === null) return elips_fail('Tanggal dibuka tidak valid.');
    $dueAt = elips_normalize_datetime($data['dueAt'] ?? null);
    if (!empty($data['dueAt']) && $dueAt === null) return elips_fail('Tenggat tugas tidak valid.');
    if ($openAt !== null && $dueAt !== null && $dueAt < $openAt) {
        return elips_fail('Tenggat harus setelah tanggal dibuka.');
    }

    $maxScore = (float) ($data['maxScore'] ?? 100);
    if ($maxScore <= 0) return elips_fail('Nilai maksimum tugas harus lebih dari 0.');
    $weight = (float) ($data['weight'] ?? 0);
    if ($weight < 0) return elips_fail('Bobot tugas tidak valid.');
    $requireFile = !empty($data['requireFile']);
    $allowedTypes = trim((string) ($data['allowedTypes'] ?? ''));
    $maxFileSize = $data['maxFileSize'] ?? null;
    $maxFileSize = ($maxFileSize === null || $maxFileSize === '') ? null : max(0, (int) $maxFileSize);

    $filesResult = elips_task_normalize_files($data['files'] ?? []);
    if (!empty($filesResult['error'])) return elips_fail($filesResult['error']);

    $db = elips_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO assignments
             (class_id, curriculum_version_id, module_id, lesson_id, title, instructions, type,
              open_at, due_at, max_score, weight, require_file, allowed_types, max_file_size,
              status, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $classId,
            (int) $class['curriculum_version_id'],
            $refs['moduleId'],
            $refs['lessonId'],
            $title,
            trim((string) ($data['instructions'] ?? '')),
            $type,
            $openAt,
            $dueAt,
            $maxScore,
            $weight,
            $requireFile ? 1 : 0,
            $allowedTypes,
            $maxFileSize,
            $status,
            (int) $actor['id'],
            (int) $actor['id'],
        ]);
        $id = (int) $db->lastInsertId();

        elips_task_save_assignment_files($id, $filesResult['files']);
        if (array_key_exists('rubrics', $data)) {
            $rubricError = elips_task_save_rubrics($id, is_array($data['rubrics']) ? $data['rubrics'] : []);
            if ($rubricError !== null) {
                $db->rollBack();
                return elips_fail($rubricError);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return elips_fail('Tugas gagal disimpan: ' . $e->getMessage());
    }

    elips_audit(
        'create',
        'assignment',
        $id,
        $title,
        'Menambahkan tugas "' . $title . '" pada kelas "' . $class['code'] . '".',
        $actor
    );

    $row = elips_assignment_row($id);
    return elips_ok(
        'Tugas berhasil ditambahkan.',
        $row ? elips_task_assignment_detail_payload($row, 0) : null
    );
}

function elips_action_assignment_update(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_assignment_context($id, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $assignment = $context['assignment'];
    $class = $context['class'];
    $data = elips_payload_array($p, 'data');

    $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $assignment['title'];
    if ($title === '') return elips_fail('Judul tugas wajib diisi.');

    $type = array_key_exists('type', $data) ? trim((string) $data['type']) : $assignment['type'];
    if (!elips_assignment_type_valid($type)) return elips_fail('Jenis tugas tidak valid.');

    $status = array_key_exists('status', $data) ? trim((string) $data['status']) : $assignment['status'];
    if (!elips_assignment_status_valid($status)) return elips_fail('Status tugas tidak valid.');

    $moduleId = array_key_exists('moduleId', $data)
        ? (int) ($data['moduleId'] ?? 0)
        : (int) ($assignment['module_id'] ?? 0);
    $lessonId = array_key_exists('lessonId', $data)
        ? (int) ($data['lessonId'] ?? 0)
        : (int) ($assignment['lesson_id'] ?? 0);
    $refs = elips_task_curriculum_refs($class, $moduleId, $lessonId);
    if (isset($refs['error'])) return elips_fail($refs['error']);

    $openAt = elips_normalize_datetime(
        array_key_exists('openAt', $data) ? $data['openAt'] : $assignment['open_at']
    );
    if (array_key_exists('openAt', $data) && !empty($data['openAt']) && $openAt === null) {
        return elips_fail('Tanggal dibuka tidak valid.');
    }
    $dueAt = elips_normalize_datetime(
        array_key_exists('dueAt', $data) ? $data['dueAt'] : $assignment['due_at']
    );
    if (array_key_exists('dueAt', $data) && !empty($data['dueAt']) && $dueAt === null) {
        return elips_fail('Tenggat tugas tidak valid.');
    }
    if ($openAt !== null && $dueAt !== null && $dueAt < $openAt) {
        return elips_fail('Tenggat harus setelah tanggal dibuka.');
    }

    $maxScore = array_key_exists('maxScore', $data)
        ? (float) $data['maxScore']
        : (float) $assignment['max_score'];
    if ($maxScore <= 0) return elips_fail('Nilai maksimum tugas harus lebih dari 0.');

    $weight = array_key_exists('weight', $data)
        ? (float) $data['weight']
        : (float) $assignment['weight'];
    if ($weight < 0) return elips_fail('Bobot tugas tidak valid.');

    $requireFile = array_key_exists('requireFile', $data)
        ? !empty($data['requireFile'])
        : (bool) $assignment['require_file'];
    $allowedTypes = array_key_exists('allowedTypes', $data)
        ? trim((string) $data['allowedTypes'])
        : $assignment['allowed_types'];
    $maxFileSize = array_key_exists('maxFileSize', $data)
        ? (($data['maxFileSize'] === null || $data['maxFileSize'] === '') ? null : max(0, (int) $data['maxFileSize']))
        : ($assignment['max_file_size'] === null ? null : (int) $assignment['max_file_size']);

    $filesResult = elips_task_normalize_files($data['files'] ?? []);
    if (!empty($filesResult['error'])) return elips_fail($filesResult['error']);

    $db = elips_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE assignments
             SET module_id = ?, lesson_id = ?, title = ?, instructions = ?, type = ?,
                 open_at = ?, due_at = ?, max_score = ?, weight = ?, require_file = ?,
                 allowed_types = ?, max_file_size = ?, status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );
        $stmt->execute([
            $refs['moduleId'],
            $refs['lessonId'],
            $title,
            array_key_exists('instructions', $data)
                ? trim((string) $data['instructions'])
                : $assignment['instructions'],
            $type,
            $openAt,
            $dueAt,
            $maxScore,
            $weight,
            $requireFile ? 1 : 0,
            $allowedTypes,
            $maxFileSize,
            $status,
            (int) $actor['id'],
            $id,
        ]);

        if (array_key_exists('files', $data)) {
            elips_task_save_assignment_files($id, $filesResult['files']);
        }
        if (array_key_exists('rubrics', $data)) {
            $rubricError = elips_task_save_rubrics($id, is_array($data['rubrics']) ? $data['rubrics'] : []);
            if ($rubricError !== null) {
                $db->rollBack();
                return elips_fail($rubricError);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return elips_fail('Tugas gagal diperbarui: ' . $e->getMessage());
    }

    elips_audit(
        'update',
        'assignment',
        $id,
        $title,
        'Memperbarui tugas "' . $title . '" pada kelas "' . $class['code'] . '".',
        $actor
    );

    $row = elips_assignment_row($id);
    return elips_ok(
        'Tugas berhasil diperbarui.',
        $row ? elips_task_assignment_detail_payload($row, 0) : null
    );
}

function elips_action_assignment_status(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_assignment_context($id, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $assignment = $context['assignment'];
    $class = $context['class'];
    $data = elips_payload_array($p, 'data');
    $status = trim((string) ($data['status'] ?? ($p['status'] ?? '')));
    if (!elips_assignment_status_valid($status)) return elips_fail('Status tugas tidak valid.');
    $reason = trim((string) ($data['reason'] ?? ($p['reason'] ?? '')));

    $stmt = elips_db()->prepare(
        'UPDATE assignments SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([$status, (int) $actor['id'], $id]);

    elips_audit(
        'status',
        'assignment',
        $id,
        $assignment['title'],
        'Mengubah status tugas "' . $assignment['title'] . '" pada kelas "' . $class['code']
            . '" dari ' . $assignment['status'] . ' menjadi ' . $status
            . ($reason !== '' ? '; alasan: ' . $reason : '') . '.',
        $actor
    );

    $row = elips_assignment_row($id);
    return elips_ok(
        'Status tugas diperbarui.',
        $row ? elips_task_assignment_detail_payload($row, 0) : null
    );
}

function elips_action_assignment_delete(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_assignment_context($id, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $assignment = $context['assignment'];
    $class = $context['class'];

    $stmt = elips_db()->prepare('SELECT COUNT(*) FROM submissions WHERE assignment_id = ?');
    $stmt->execute([$id]);
    $submissionCount = (int) $stmt->fetchColumn();

    $stmt = elips_db()->prepare('DELETE FROM assignments WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'assignment',
        $id,
        $assignment['title'],
        'Menghapus tugas "' . $assignment['title'] . '" dari kelas "' . $class['code']
            . '" beserta ' . $submissionCount . ' pengumpulan.',
        $actor
    );

    return elips_ok('Tugas dihapus.', [
        'id' => $id,
        'removedSubmissions' => $submissionCount,
    ]);
}

function elips_action_assignment_submissions(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_assignment_context($id, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $assignment = $context['assignment'];
    $class = $context['class'];

    $stmt = elips_db()->prepare(
        "SELECT e.id, e.student_id, e.status, u.name AS student_name
         FROM enrollments e
         JOIN users u ON u.id = e.student_id
         WHERE e.class_id = ? AND e.status IN ('active', 'completed')
         ORDER BY u.name ASC, e.id ASC"
    );
    $stmt->execute([(int) $class['id']]);

    $students = [];
    foreach ($stmt->fetchAll() as $enrollment) {
        $submissionRow = elips_assignment_submission_for($id, (int) $enrollment['id']);
        $submission = null;
        if ($submissionRow) {
            $public = elips_submission_row((int) $submissionRow['id']);
            if ($public) $submission = elips_submission_public($public, false);
        }
        $students[] = [
            'enrollmentId' => (int) $enrollment['id'],
            'studentId' => (int) $enrollment['student_id'],
            'studentName' => $enrollment['student_name'],
            'enrollmentStatus' => $enrollment['status'],
            'submission' => $submission,
        ];
    }

    return elips_ok('Daftar pengumpulan tugas dimuat.', [
        'assignment' => elips_assignment_public($assignment, true),
        'students' => $students,
        'stats' => elips_assignment_stats($id),
    ]);
}

function elips_action_assignment_submit(array $p): array
{
    $actor = elips_require_login();
    $assignmentId = (int) ($p['assignmentId'] ?? 0);
    $context = elips_task_assignment_context($assignmentId, $actor);
    if (isset($context['error'])) return elips_fail($context['error']);
    if ($context['staff']) return elips_fail('Pengumpulan tugas dilakukan oleh peserta.');

    $assignment = $context['assignment'];
    $class = $context['class'];
    $enrollment = $context['enrollment'];
    $now = gmdate('Y-m-d H:i:s');

    if ($assignment['status'] !== 'published') {
        return elips_fail('Tugas ini sudah ditutup untuk pengumpulan.');
    }
    if ($assignment['open_at'] !== null && $assignment['open_at'] > $now) {
        return elips_fail('Tugas belum dibuka.');
    }
    if ($assignment['type'] === 'in_class_practice') {
        return elips_fail('Tugas praktik kelas dicatat langsung oleh instruktur.');
    }

    $data = elips_payload_array($p, 'data');
    $textAnswer = trim((string) ($data['textAnswer'] ?? ''));
    $filesResult = elips_task_normalize_files($data['files'] ?? []);
    if (!empty($filesResult['error'])) return elips_fail($filesResult['error']);
    $files = $filesResult['files'];

    $existingRow = elips_assignment_submission_for($assignmentId, (int) $enrollment['id']);
    if ($existingRow && in_array($existingRow['status'], ['reviewed', 'accepted'], true)) {
        return elips_fail('Pengumpulan ini sudah dinilai. Tunggu instruktur membuka revisi.');
    }

    $existingFiles = $existingRow ? elips_submission_files_for((int) $existingRow['id'], true) : [];
    $willHaveFiles = array_key_exists('files', $data) ? count($files) > 0 : count($existingFiles) > 0;
    if ($textAnswer === '' && !$willHaveFiles) {
        return elips_fail('Jawaban teks atau file tugas wajib diisi.');
    }
    if ($assignment['type'] === 'text_submission' && $textAnswer === '') {
        return elips_fail('Jawaban teks tugas wajib diisi.');
    }
    if ($assignment['type'] === 'external_reference' && $textAnswer === '' && !$willHaveFiles) {
        return elips_fail('Tautan atau bukti penyelesaian tugas wajib diisi.');
    }
    if (!empty($assignment['require_file']) && !$willHaveFiles) {
        return elips_fail('File tugas wajib diunggah.');
    }
    foreach ($files as $file) {
        $fileError = elips_task_allowed_file_error($file['fileName'], $assignment);
        if ($fileError !== null) return elips_fail($fileError);
        if ($assignment['max_file_size'] !== null
            && $file['fileSize'] !== null
            && $file['fileSize'] > (int) $assignment['max_file_size']) {
            return elips_fail('Ukuran file melebihi batas yang diizinkan.');
        }
    }

    $isLate = $assignment['due_at'] !== null && $assignment['due_at'] < $now;
    $revisionRequested = $existingRow && $existingRow['status'] === 'revision_requested';
    $status = $revisionRequested ? 'resubmitted' : ($isLate ? 'late' : 'submitted');
    $revisionCount = $existingRow ? (int) $existingRow['revision_count'] : 0;
    if ($revisionRequested) $revisionCount++;

    $db = elips_db();
    $db->beginTransaction();
    try {
        if ($existingRow) {
            $stmt = $db->prepare(
                'UPDATE submissions
                 SET status = ?, text_answer = ?, submitted_at = UTC_TIMESTAMP(), is_late = ?,
                     revision_count = ?, updated_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $stmt->execute([
                $status,
                $textAnswer,
                $isLate ? 1 : 0,
                $revisionCount,
                (int) $existingRow['id'],
            ]);
            $submissionId = (int) $existingRow['id'];
        } else {
            $stmt = $db->prepare(
                'INSERT INTO submissions
                 (assignment_id, enrollment_id, student_id, status, text_answer, submitted_at, is_late,
                  feedback, revision_count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmt->execute([
                $assignmentId,
                (int) $enrollment['id'],
                (int) $enrollment['student_id'],
                $status,
                $textAnswer,
                $isLate ? 1 : 0,
                '',
                $revisionCount,
            ]);
            $submissionId = (int) $db->lastInsertId();
        }

        if (array_key_exists('files', $data)) {
            elips_task_save_submission_files($submissionId, $files);
        }

        $stmt = $db->prepare(
            'INSERT INTO submission_reviews (submission_id, reviewer_id, action, score, note, created_at)
             VALUES (?, ?, ?, NULL, ?, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $submissionId,
            (int) $actor['id'],
            'submit',
            $revisionRequested ? 'Mengumpulkan ulang tugas setelah revisi.' : 'Mengumpulkan tugas.',
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return elips_fail('Tugas gagal dikumpulkan: ' . $e->getMessage());
    }

    elips_audit(
        $revisionRequested ? 'update' : 'submit',
        'submission',
        $submissionId,
        $assignment['title'],
        'Peserta #' . $enrollment['student_id'] . ' mengumpulkan tugas "' . $assignment['title']
            . '" pada kelas "' . $class['code'] . '"' . ($isLate ? ' (terlambat)' : '') . '.',
        $actor
    );

    $row = elips_submission_row($submissionId);
    if (!$row) return elips_fail('Pengumpulan tugas tidak dapat dimuat.');
    $submission = elips_submission_public($row, true);
    $submission['files'] = elips_submission_files_for($submissionId, true);
    $submission['reviews'] = elips_submission_reviews_for($submissionId);
    return elips_ok(
        $isLate ? 'Tugas dikumpulkan (terlambat).' : 'Tugas berhasil dikumpulkan.',
        $submission
    );
}

function elips_action_assignment_review(array $p): array
{
    $actor = elips_require_login();
    $submissionId = (int) ($p['submissionId'] ?? 0);
    $submissionRow = elips_submission_row($submissionId);
    if (!$submissionRow) return elips_fail('Pengumpulan tugas tidak ditemukan.');

    $context = elips_task_assignment_context((int) $submissionRow['assignment_id'], $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $assignment = $context['assignment'];
    $data = elips_payload_array($p, 'data');
    $action = trim((string) ($data['action'] ?? 'score'));
    if (!in_array($action, ['score', 'revision', 'accept'], true)) {
        return elips_fail('Aksi penilaian tidak valid.');
    }

    $maxScore = (float) $submissionRow['max_score'];
    $score = null;
    if (array_key_exists('score', $data) && $data['score'] !== '' && $data['score'] !== null) {
        $score = (float) $data['score'];
        if ($score < 0 || $score > $maxScore) {
            return elips_fail('Nilai harus antara 0 dan ' . $maxScore . '.');
        }
    }
    if ($action === 'score' && $score === null) {
        return elips_fail('Nilai wajib diisi untuk penilaian.');
    }

    $note = trim((string) ($data['note'] ?? $data['feedback'] ?? ''));
    if ($action === 'revision' && $note === '') {
        return elips_fail('Catatan revisi wajib diisi.');
    }

    $status = $action === 'revision' ? 'revision_requested' : ($action === 'accept' ? 'accepted' : 'reviewed');
    $feedback = array_key_exists('feedback', $data)
        ? trim((string) $data['feedback'])
        : ($note !== '' ? $note : $submissionRow['feedback']);

    $db = elips_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'UPDATE submissions
             SET status = ?, score = ?, feedback = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );
        $stmt->execute([$status, $score, $feedback, (int) $actor['id'], $submissionId]);

        $stmt = $db->prepare(
            'INSERT INTO submission_reviews (submission_id, reviewer_id, action, score, note, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $stmt->execute([$submissionId, (int) $actor['id'], $action, $score, $note]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return elips_fail('Penilaian tugas gagal disimpan: ' . $e->getMessage());
    }

    elips_audit(
        $action === 'revision' ? 'revision' : ($action === 'accept' ? 'approve' : 'grade'),
        'submission',
        $submissionId,
        $assignment['title'],
        'Memberi ' . ($action === 'revision' ? 'permintaan revisi' : ($action === 'accept' ? 'persetujuan' : 'nilai'))
            . ' untuk pengumpulan tugas "' . $assignment['title'] . '" peserta #' . $submissionRow['student_id']
            . ($score !== null ? ' dengan nilai ' . $score : '') . '.',
        $actor
    );

    $row = elips_submission_row($submissionId);
    if (!$row) return elips_fail('Pengumpulan tugas tidak dapat dimuat.');
    $submission = elips_submission_public($row, true);
    $submission['files'] = elips_submission_files_for($submissionId, true);
    $submission['reviews'] = elips_submission_reviews_for($submissionId);
    return elips_ok('Penilaian tugas disimpan.', $submission);
}

function elips_action_exam_classroom(array $p): array
{
    $actor = elips_require_login();
    $context = elips_task_classroom_context($p, $actor);
    if (isset($context['error'])) return elips_fail($context['error']);

    $payload = elips_assignment_classroom_payload(
        $context['class'],
        $context['enrollment'],
        $context['preview']
    );
    return elips_ok('Ruang ujian dimuat.', [
        'class' => $payload['class'],
        'enrollment' => $payload['enrollment'],
        'preview' => $payload['preview'],
        'exams' => $payload['exams'],
        'stats' => [
            'examTotal' => $payload['stats']['examTotal'],
            'examGraded' => $payload['stats']['examGraded'],
        ],
    ]);
}

function elips_action_exam_detail(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_exam_context($id, $actor);
    if (isset($context['error'])) return elips_fail($context['error']);

    $enrollmentId = 0;
    if ($context['staff']) {
        $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
        if ($enrollmentId > 0
            && !elips_class_enrollment_by_id((int) $context['class']['id'], $enrollmentId)) {
            return elips_fail('Peserta tidak terdaftar pada kelas ini.');
        }
        if ($enrollmentId <= 0 && !empty($p['studentId'])) {
            $enrollment = elips_class_enrollment_for(
                (int) $context['class']['id'],
                (int) $p['studentId']
            );
            $enrollmentId = $enrollment ? (int) $enrollment['id'] : 0;
        }
    } else {
        $enrollmentId = (int) $context['enrollment']['id'];
    }

    return elips_ok(
        'Detail ujian dimuat.',
        elips_task_exam_detail_payload($context['exam'], $context['staff'], $enrollmentId)
    );
}

function elips_action_exam_create(array $p): array
{
    $actor = elips_require_login();
    $classId = (int) ($p['classId'] ?? 0);
    $class = elips_class_row($classId);
    if (!$class) return elips_fail('Kelas tidak ditemukan.');
    if (!elips_can_manage_class($class, $actor)) {
        return elips_fail('Anda tidak berhak menambahkan ujian pada kelas ini.');
    }

    $data = elips_payload_array($p, 'data');
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') return elips_fail('Judul ujian wajib diisi.');

    $type = trim((string) ($data['type'] ?? 'written'));
    if (!elips_exam_type_valid($type)) return elips_fail('Jenis ujian tidak valid.');

    $status = trim((string) ($data['status'] ?? 'draft'));
    if (!elips_exam_status_valid($status)) return elips_fail('Status ujian tidak valid.');

    $examDate = elips_normalize_date($data['examDate'] ?? null);
    if (!empty($data['examDate']) && $examDate === null) return elips_fail('Tanggal ujian tidak valid.');
    $startTime = elips_time_normalized($data['startTime'] ?? null);
    if (!empty($data['startTime']) && $startTime === null) return elips_fail('Waktu mulai ujian tidak valid.');
    $duration = $data['durationMinutes'] ?? null;
    $duration = ($duration === null || $duration === '') ? null : max(0, (int) $duration);
    $maxScore = (float) ($data['maxScore'] ?? 100);
    if ($maxScore <= 0) return elips_fail('Nilai maksimum ujian harus lebih dari 0.');
    $weight = (float) ($data['weight'] ?? 0);
    if ($weight < 0) return elips_fail('Bobot ujian tidak valid.');

    $db = elips_db();
    $stmt = $db->prepare(
        'INSERT INTO exams
         (class_id, curriculum_version_id, title, description, type, exam_date, start_time,
          duration_minutes, max_score, weight, instructions, status, created_by, updated_by,
          created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $classId,
        (int) $class['curriculum_version_id'],
        $title,
        trim((string) ($data['description'] ?? '')),
        $type,
        $examDate,
        $startTime,
        $duration,
        $maxScore,
        $weight,
        trim((string) ($data['instructions'] ?? '')),
        $status,
        (int) $actor['id'],
        (int) $actor['id'],
    ]);
    $id = (int) $db->lastInsertId();
    elips_exam_ensure_roster($id, $classId);

    elips_audit(
        'create',
        'exam',
        $id,
        $title,
        'Menambahkan ujian "' . $title . '" pada kelas "' . $class['code'] . '".',
        $actor
    );

    $row = elips_exam_row($id);
    return elips_ok(
        'Ujian berhasil ditambahkan.',
        $row ? elips_task_exam_detail_payload($row, true, 0) : null
    );
}

function elips_action_exam_update(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_exam_context($id, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $exam = $context['exam'];
    $class = $context['class'];
    $data = elips_payload_array($p, 'data');

    $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $exam['title'];
    if ($title === '') return elips_fail('Judul ujian wajib diisi.');
    $type = array_key_exists('type', $data) ? trim((string) $data['type']) : $exam['type'];
    if (!elips_exam_type_valid($type)) return elips_fail('Jenis ujian tidak valid.');
    $status = array_key_exists('status', $data) ? trim((string) $data['status']) : $exam['status'];
    if (!elips_exam_status_valid($status)) return elips_fail('Status ujian tidak valid.');

    $examDate = elips_normalize_date(
        array_key_exists('examDate', $data) ? $data['examDate'] : $exam['exam_date']
    );
    if (array_key_exists('examDate', $data) && !empty($data['examDate']) && $examDate === null) {
        return elips_fail('Tanggal ujian tidak valid.');
    }
    $startTime = elips_time_normalized(
        array_key_exists('startTime', $data) ? $data['startTime'] : $exam['start_time']
    );
    if (array_key_exists('startTime', $data) && !empty($data['startTime']) && $startTime === null) {
        return elips_fail('Waktu mulai ujian tidak valid.');
    }
    $duration = array_key_exists('durationMinutes', $data)
        ? (($data['durationMinutes'] === null || $data['durationMinutes'] === '')
            ? null : max(0, (int) $data['durationMinutes']))
        : ($exam['duration_minutes'] === null ? null : (int) $exam['duration_minutes']);
    $maxScore = array_key_exists('maxScore', $data)
        ? (float) $data['maxScore']
        : (float) $exam['max_score'];
    if ($maxScore <= 0) return elips_fail('Nilai maksimum ujian harus lebih dari 0.');
    $weight = array_key_exists('weight', $data)
        ? (float) $data['weight']
        : (float) $exam['weight'];
    if ($weight < 0) return elips_fail('Bobot ujian tidak valid.');

    $stmt = elips_db()->prepare(
        'UPDATE exams
         SET title = ?, description = ?, type = ?, exam_date = ?, start_time = ?,
             duration_minutes = ?, max_score = ?, weight = ?, instructions = ?, status = ?,
             updated_by = ?, updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $stmt->execute([
        $title,
        array_key_exists('description', $data)
            ? trim((string) $data['description'])
            : $exam['description'],
        $type,
        $examDate,
        $startTime,
        $duration,
        $maxScore,
        $weight,
        array_key_exists('instructions', $data)
            ? trim((string) $data['instructions'])
            : $exam['instructions'],
        $status,
        (int) $actor['id'],
        $id,
    ]);
    elips_exam_ensure_roster($id, (int) $class['id']);

    elips_audit(
        'update',
        'exam',
        $id,
        $title,
        'Memperbarui ujian "' . $title . '" pada kelas "' . $class['code'] . '".',
        $actor
    );

    $row = elips_exam_row($id);
    return elips_ok(
        'Ujian berhasil diperbarui.',
        $row ? elips_task_exam_detail_payload($row, true, 0) : null
    );
}

function elips_action_exam_status(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_exam_context($id, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $exam = $context['exam'];
    $class = $context['class'];
    $data = elips_payload_array($p, 'data');
    $status = trim((string) ($data['status'] ?? ($p['status'] ?? '')));
    if (!elips_exam_status_valid($status)) return elips_fail('Status ujian tidak valid.');
    $reason = trim((string) ($data['reason'] ?? ($p['reason'] ?? '')));

    $stmt = elips_db()->prepare(
        'UPDATE exams SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([$status, (int) $actor['id'], $id]);

    elips_audit(
        'status',
        'exam',
        $id,
        $exam['title'],
        'Mengubah status ujian "' . $exam['title'] . '" pada kelas "' . $class['code']
            . '" dari ' . $exam['status'] . ' menjadi ' . $status
            . ($reason !== '' ? '; alasan: ' . $reason : '') . '.',
        $actor
    );

    $row = elips_exam_row($id);
    return elips_ok(
        'Status ujian diperbarui.',
        $row ? elips_task_exam_detail_payload($row, true, 0) : null
    );
}

function elips_action_exam_delete(array $p): array
{
    $actor = elips_require_login();
    $id = (int) ($p['id'] ?? 0);
    $context = elips_task_exam_context($id, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $exam = $context['exam'];
    $class = $context['class'];

    $stmt = elips_db()->prepare("SELECT COUNT(*) FROM exam_results WHERE exam_id = ? AND status = 'graded'");
    $stmt->execute([$id]);
    $gradedCount = (int) $stmt->fetchColumn();

    $stmt = elips_db()->prepare('DELETE FROM exams WHERE id = ?');
    $stmt->execute([$id]);

    elips_audit(
        'delete',
        'exam',
        $id,
        $exam['title'],
        'Menghapus ujian "' . $exam['title'] . '" dari kelas "' . $class['code']
            . '" beserta ' . $gradedCount . ' nilai peserta.',
        $actor
    );

    return elips_ok('Ujian dihapus.', [
        'id' => $id,
        'removedResults' => $gradedCount,
    ]);
}

function elips_action_exam_result(array $p): array
{
    $actor = elips_require_login();
    $examId = (int) ($p['examId'] ?? 0);
    $context = elips_task_exam_context($examId, $actor, true);
    if (isset($context['error'])) return elips_fail($context['error']);

    $exam = $context['exam'];
    $class = $context['class'];
    elips_exam_ensure_roster($examId, (int) $class['id']);

    $enrollment = null;
    $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
    if ($enrollmentId > 0) {
        $enrollment = elips_class_enrollment_by_id((int) $class['id'], $enrollmentId);
    } elseif (!empty($p['studentId'])) {
        $enrollment = elips_class_enrollment_for((int) $class['id'], (int) $p['studentId']);
    }
    if (!$enrollment) return elips_fail('Peserta tidak terdaftar pada kelas ini.');

    $data = elips_payload_array($p, 'data');
    if (!$data) $data = $p;
    $status = trim((string) ($data['status'] ?? 'graded'));
    if (!in_array($status, ['not_assessed', 'excused', 'missing', 'graded'], true)) {
        return elips_fail('Status hasil ujian tidak valid.');
    }
    $maxScore = (float) $exam['max_score'];
    $score = null;
    if (array_key_exists('score', $data) && $data['score'] !== null && $data['score'] !== '') {
        $score = (float) $data['score'];
        if ($score < 0 || $score > $maxScore) {
            return elips_fail('Nilai ujian harus antara 0 dan ' . $maxScore . '.');
        }
    }
    if ($status === 'graded' && $score === null) {
        return elips_fail('Nilai wajib diisi untuk hasil yang dinilai.');
    }
    $note = trim((string) ($data['note'] ?? ''));

    $participantStatus = trim((string) ($data['participantStatus'] ?? ''));
    $validParticipantStatuses = ['registered', 'attended', 'absent', 'excused'];
    if ($participantStatus !== '' && !in_array($participantStatus, $validParticipantStatuses, true)) {
        return elips_fail('Status keikutsertaan ujian tidak valid.');
    }
    if ($participantStatus === '') {
        if ($status === 'graded') $participantStatus = 'attended';
        elseif ($status === 'missing') $participantStatus = 'absent';
        elseif ($status === 'excused') $participantStatus = 'excused';
        else $participantStatus = 'registered';
    }

    $db = elips_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM exam_results WHERE exam_id = ? AND enrollment_id = ? LIMIT 1');
        $stmt->execute([$examId, (int) $enrollment['id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare(
                'UPDATE exam_results
                 SET score = ?, status = ?, graded_by = ?, graded_at = ?, note = ?, updated_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $stmt->execute([
                $score,
                $status,
                $status === 'not_assessed' ? null : (int) $actor['id'],
                $status === 'not_assessed' ? null : gmdate('Y-m-d H:i:s'),
                $note,
                (int) $existing['id'],
            ]);
            $resultId = (int) $existing['id'];
        } else {
            $stmt = $db->prepare(
                'INSERT INTO exam_results
                 (exam_id, enrollment_id, student_id, score, status, graded_by, graded_at, note, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmt->execute([
                $examId,
                (int) $enrollment['id'],
                (int) $enrollment['student_id'],
                $score,
                $status,
                $status === 'not_assessed' ? null : (int) $actor['id'],
                $status === 'not_assessed' ? null : gmdate('Y-m-d H:i:s'),
                $note,
            ]);
            $resultId = (int) $db->lastInsertId();
        }

        $stmt = $db->prepare('SELECT * FROM exam_participants WHERE exam_id = ? AND enrollment_id = ? LIMIT 1');
        $stmt->execute([$examId, (int) $enrollment['id']]);
        $participant = $stmt->fetch();
        if ($participant) {
            $stmt = $db->prepare(
                'UPDATE exam_participants SET status = ?, note = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
            );
            $stmt->execute([$participantStatus, $note, (int) $participant['id']]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO exam_participants
                 (exam_id, enrollment_id, student_id, status, note, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmt->execute([
                $examId,
                (int) $enrollment['id'],
                (int) $enrollment['student_id'],
                $participantStatus,
                $note,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return elips_fail('Hasil ujian gagal disimpan: ' . $e->getMessage());
    }

    elips_audit(
        'grade',
        'exam_result',
        $resultId,
        $exam['title'],
        'Menyimpan hasil ujian "' . $exam['title'] . '" untuk peserta #' . $enrollment['student_id']
            . ' dengan status ' . $status . ($score !== null ? ' dan nilai ' . $score : '') . '.',
        $actor
    );

    $row = elips_exam_result_for($examId, (int) $enrollment['id']);
    return elips_ok('Hasil ujian disimpan.', $row ? elips_exam_result_public($row) : null);
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
        $stmt = $db->prepare('SELECT * FROM grades WHERE enrollment_id = ? LIMIT 1');
        $stmt->execute([$enrollmentId]);
        $grade = $stmt->fetch() ?: null;
        if (!$grade) {
            $stmt = $db->prepare('SELECT * FROM grades WHERE student_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$studentId, $courseId]);
            $grade = $stmt->fetch() ?: null;
        }
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

        $stmt = $db->prepare('SELECT * FROM certificates WHERE enrollment_id = ? LIMIT 1');
        $stmt->execute([$enrollmentId]);
        $certificate = $stmt->fetch() ?: null;
        if (!$certificate) {
            $stmt = $db->prepare('SELECT * FROM certificates WHERE student_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$studentId, $courseId]);
            $certificate = $stmt->fetch() ?: null;
        }
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
    $stmt = $db->prepare('SELECT id FROM certificates WHERE enrollment_id = ? LIMIT 1');
    $stmt->execute([$enrollmentId]);
    if ($stmt->fetch()) return elips_fail('Sertifikat peserta ini sudah ada.');

    $stmt = $db->prepare('SELECT * FROM grades WHERE enrollment_id = ? LIMIT 1');
    $stmt->execute([$enrollmentId]);
    $grade = $stmt->fetch();
    if (!$grade) {
        $stmt = $db->prepare('SELECT * FROM grades WHERE student_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([(int) $enrollment['student_id'], (int) $enrollment['course_id']]);
        $grade = $stmt->fetch();
    }
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

    $version = elips_current_curriculum_version($courseId);
    if (!$version) return elips_fail('Kursus belum memiliki kurikulum.');
    $stmt = $db->prepare('SELECT * FROM lessons WHERE id = ? AND course_id = ? AND curriculum_version_id = ? LIMIT 1');
    $stmt->execute([$lessonId, $courseId, (int) $version['id']]);
    $lesson = $stmt->fetch();
    if (!$lesson) return elips_fail('Materi tidak ditemukan pada kurikulum kursus ini.');

    // Urutan materi mengikuti urutan modul lalu urutan materi.
    $stmt = $db->prepare(
        'SELECT l.id FROM lessons l JOIN modules m ON m.id = l.module_id
         WHERE l.course_id = ? AND l.curriculum_version_id = ?
         ORDER BY m.sort_order ASC, m.id ASC, l.sort_order ASC, l.id ASC'
    );
    $stmt->execute([$courseId, (int) $version['id']]);
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
