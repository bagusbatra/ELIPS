<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - snapshot data untuk client
// Mengembalikan seluruh koleksi dalam bentuk yang sama dengan
// model data JavaScript, sehingga UI dapat merender secara
// sinkron dari cache.
// ============================================================

function elips_snapshot(): array
{
    $db = elips_db();

    $users = [];
    foreach ($db->query('SELECT * FROM users ORDER BY id ASC') as $row) {
        $users[] = elips_serialize_user($row);
    }

    $courses = [];
    $courseSql = 'SELECT c.*, u.name AS instructor_name,
            (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) AS module_count,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
        FROM courses c
        LEFT JOIN users u ON u.id = c.instructor_id
        ORDER BY c.id ASC';
    foreach ($db->query($courseSql) as $row) {
        $lessonCount = (int) $row['lesson_count'];
        $courses[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'description' => $row['description'],
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'instructorName' => $row['instructor_name'] ?? '',
            'modules' => (int) $row['module_count'],
            'lessons' => $lessonCount,
            'totalLessons' => $lessonCount,
            'status' => $row['status'],
            'createdAt' => elips_date_only($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $modules = [];
    foreach ($db->query('SELECT * FROM modules ORDER BY course_id ASC, sort_order ASC, id ASC') as $row) {
        $modules[] = [
            'id' => (int) $row['id'],
            'courseId' => (int) $row['course_id'],
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'order' => (int) $row['sort_order'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $lessons = [];
    foreach ($db->query('SELECT * FROM lessons ORDER BY course_id ASC, module_id ASC, sort_order ASC, id ASC') as $row) {
        $lessons[] = [
            'id' => (int) $row['id'],
            'moduleId' => (int) $row['module_id'],
            'courseId' => (int) $row['course_id'],
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'title' => $row['title'],
            'type' => $row['type'],
            'content' => $row['content'],
            'order' => (int) $row['sort_order'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $enrollments = [];
    foreach ($db->query('SELECT * FROM enrollments ORDER BY id ASC') as $row) {
        $enrollments[] = [
            'id' => (int) $row['id'],
            'studentId' => (int) $row['student_id'],
            'courseId' => (int) $row['course_id'],
            'enrolledBy' => $row['enrolled_by'],
            'enrolledAt' => elips_date_only($row['enrolled_at']),
            'status' => $row['status'],
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $grades = [];
    foreach ($db->query('SELECT * FROM grades ORDER BY id ASC') as $row) {
        $grades[] = [
            'id' => (int) $row['id'],
            'enrollmentId' => $row['enrollment_id'] === null ? null : (int) $row['enrollment_id'],
            'courseId' => (int) $row['course_id'],
            'studentId' => (int) $row['student_id'],
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'score' => (int) $row['score'],
            'grade' => $row['grade'],
            'note' => $row['note'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $certificates = [];
    foreach ($db->query('SELECT * FROM certificates ORDER BY id ASC') as $row) {
        $certificates[] = [
            'id' => (int) $row['id'],
            'studentId' => (int) $row['student_id'],
            'courseId' => (int) $row['course_id'],
            'enrollmentId' => $row['enrollment_id'] === null ? null : (int) $row['enrollment_id'],
            'certificateNumber' => $row['certificate_number'],
            'status' => $row['status'],
            'grade' => $row['grade'],
            'score' => $row['score'] === null ? null : (int) $row['score'],
            'issuedByInstructorAt' => elips_iso($row['issued_by_instructor_at']),
            'approvedByAdminId' => $row['approved_by_admin_id'] === null ? null : (int) $row['approved_by_admin_id'],
            'approvedAt' => elips_iso($row['approved_at']),
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $completedLessons = [];
    foreach ($db->query('SELECT * FROM completed_lessons ORDER BY id ASC') as $row) {
        $completedLessons[] = [
            'id' => (int) $row['id'],
            'studentId' => (int) $row['student_id'],
            'courseId' => (int) $row['course_id'],
            'lessonId' => (int) $row['lesson_id'],
            'completedAt' => elips_iso($row['completed_at']),
        ];
    }

    $auditLimit = (int) elips_config()['audit_limit'];
    $auditLogs = [];
    foreach ($db->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT ' . $auditLimit) as $row) {
        $auditLogs[] = [
            'id' => (int) $row['id'],
            'action' => $row['action'],
            'entity' => $row['entity'],
            'entityId' => $row['entity_id'] === null ? null : (int) $row['entity_id'],
            'entityLabel' => $row['entity_label'],
            'actorId' => $row['actor_id'] === null ? null : (int) $row['actor_id'],
            'actorName' => $row['actor_name'],
            'actorRole' => $row['actor_role'],
            'detail' => $row['detail'],
            'createdAt' => elips_iso($row['created_at']),
        ];
    }

    $notificationLimit = (int) elips_config()['notification_limit'];
    $notifications = [];
    foreach ($db->query('SELECT * FROM notifications ORDER BY id DESC LIMIT ' . $notificationLimit) as $row) {
        $notifications[] = [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'channel' => $row['channel'],
            'simulated' => (bool) $row['simulated'],
            'status' => $row['status'],
            'recipientId' => $row['recipient_id'] === null ? null : (int) $row['recipient_id'],
            'recipientName' => $row['recipient_name'],
            'recipientEmail' => $row['recipient_email'],
            'recipientRole' => $row['recipient_role'],
            'subject' => $row['subject'],
            'body' => $row['body'],
            'entity' => $row['entity'],
            'entityId' => $row['entity_id'] === null ? null : (int) $row['entity_id'],
            'entityLabel' => $row['entity_label'],
            'createdAt' => elips_iso($row['created_at']),
        ];
    }

    return [
        'users' => $users,
        'courses' => $courses,
        'enrollments' => $enrollments,
        'modules' => $modules,
        'lessons' => $lessons,
        'grades' => $grades,
        'certificates' => $certificates,
        'completedLessons' => $completedLessons,
        'auditLogs' => $auditLogs,
        'notifications' => $notifications,
    ];
}
