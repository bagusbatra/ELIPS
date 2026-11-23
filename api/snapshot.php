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

    $categories = [];
    foreach ($db->query('SELECT * FROM categories ORDER BY id ASC') as $row) {
        $categories[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'status' => $row['status'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $branches = [];
    foreach ($db->query('SELECT id FROM branches ORDER BY id ASC') as $row) {
        $branch = elips_branch_public((int) $row['id']);
        if ($branch) $branches[] = $branch;
    }

    $courses = [];
    foreach ($db->query('SELECT id FROM courses ORDER BY id ASC') as $row) {
        $courses[] = elips_course_public((int) $row['id']);
    }

    $curriculumVersions = [];
    $versionRows = $db->query(
        'SELECT cv.*, c.title AS course_title,
            (SELECT COUNT(*) FROM modules m WHERE m.curriculum_version_id = cv.id) AS module_count,
            (SELECT COUNT(*) FROM lessons l WHERE l.curriculum_version_id = cv.id) AS lesson_count,
            (SELECT COUNT(*) FROM classes cl WHERE cl.curriculum_version_id = cv.id) AS class_count
         FROM curriculum_versions cv
         JOIN courses c ON c.id = cv.course_id
         ORDER BY cv.course_id ASC, cv.id ASC'
    );
    $currentVersionIds = [];
    foreach ($db->query(
        "SELECT c.id AS course_id,
            (SELECT cv.id FROM curriculum_versions cv
             WHERE cv.course_id = c.id AND cv.status <> 'retired'
             ORDER BY cv.id DESC LIMIT 1) AS version_id
         FROM courses c"
    ) as $row) {
        if ($row['version_id'] !== null) {
            $currentVersionIds[(int) $row['course_id']] = (int) $row['version_id'];
        }
    }

    foreach ($versionRows as $row) {
        $curriculumVersions[] = [
            'id' => (int) $row['id'],
            'courseId' => (int) $row['course_id'],
            'courseTitle' => $row['course_title'],
            'code' => $row['code'],
            'name' => $row['name'],
            'description' => $row['description'],
            'status' => $row['status'],
            'effectiveDate' => elips_date_only($row['effective_date']),
            'notes' => $row['notes'],
            'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
            'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
            'modules' => (int) $row['module_count'],
            'lessons' => (int) $row['lesson_count'],
            'classes' => (int) $row['class_count'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $modules = [];
    foreach ($db->query('SELECT * FROM modules ORDER BY curriculum_version_id ASC, sort_order ASC, id ASC') as $row) {
        $versionId = (int) $row['curriculum_version_id'];
        if (!in_array($versionId, $currentVersionIds, true)) {
            continue;
        }
        $modules[] = [
            'id' => (int) $row['id'],
            'courseId' => (int) $row['course_id'],
            'curriculumVersionId' => $versionId,
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'order' => (int) $row['sort_order'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $lessons = [];
    foreach ($db->query('SELECT * FROM lessons ORDER BY curriculum_version_id ASC, module_id ASC, sort_order ASC, id ASC') as $row) {
        $versionId = (int) $row['curriculum_version_id'];
        if (!in_array($versionId, $currentVersionIds, true)) {
            continue;
        }
        $lessons[] = [
            'id' => (int) $row['id'],
            'moduleId' => (int) $row['module_id'],
            'courseId' => (int) $row['course_id'],
            'curriculumVersionId' => $versionId,
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'title' => $row['title'],
            'type' => $row['type'],
            'content' => $row['content'],
            'order' => (int) $row['sort_order'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $classes = [];
    $classRows = $db->query(
        "SELECT cl.*, c.title AS course_title, u.name AS instructor_name,
            cv.code AS version_code, cv.name AS version_name,
            b.code AS branch_code, b.name AS branch_name, b.city AS branch_city,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id AND e.status IN ('active', 'pending')) AS active_count,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id) AS enrollment_count
         FROM classes cl
         JOIN courses c ON c.id = cl.course_id
         LEFT JOIN users u ON u.id = cl.instructor_id
         LEFT JOIN curriculum_versions cv ON cv.id = cl.curriculum_version_id
         LEFT JOIN branches b ON b.id = cl.branch_id
         ORDER BY cl.id ASC"
    );
    foreach ($classRows as $row) {
        $classes[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'courseId' => (int) $row['course_id'],
            'courseTitle' => $row['course_title'],
            'curriculumVersionId' => (int) $row['curriculum_version_id'],
            'curriculumVersionCode' => $row['version_code'],
            'curriculumVersionName' => $row['version_name'],
            'branchId' => $row['branch_id'] === null ? null : (int) $row['branch_id'],
            'branchCode' => $row['branch_code'] ?? '',
            'branchName' => $row['branch_name'] ?? '',
            'branchCity' => $row['branch_city'] ?? '',
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'instructorName' => $row['instructor_name'] ?? '',
            'periodStart' => elips_date_only($row['period_start']),
            'periodEnd' => elips_date_only($row['period_end']),
            'quota' => (int) $row['quota'],
            'location' => $row['location'],
            'room' => $row['room'],
            'scheduleNote' => $row['schedule_note'],
            'plannedJp' => (float) $row['planned_jp'],
            'status' => $row['status'],
            'legacyImport' => (bool) $row['legacy_import'],
            'notes' => $row['notes'],
            'enrollmentCount' => (int) $row['enrollment_count'],
            'activeEnrollmentCount' => (int) $row['active_count'],
            'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
            'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $classInstructorHistories = [];
    $historyRows = $db->query(
        'SELECT h.*, u.name AS instructor_name, a.name AS actor_name
         FROM class_instructor_histories h
         LEFT JOIN users u ON u.id = h.instructor_id
         LEFT JOIN users a ON a.id = h.actor_id
         ORDER BY h.class_id ASC, h.started_at ASC, h.id ASC'
    );
    foreach ($historyRows as $row) {
        $classInstructorHistories[] = [
            'id' => (int) $row['id'],
            'classId' => (int) $row['class_id'],
            'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
            'instructorName' => $row['instructor_name'] ?? '',
            'startedAt' => elips_date_only($row['started_at']),
            'endedAt' => elips_date_only($row['ended_at']),
            'reason' => $row['reason'],
            'actorId' => $row['actor_id'] === null ? null : (int) $row['actor_id'],
            'actorName' => $row['actor_name'] ?? '',
            'createdAt' => elips_iso($row['created_at']),
        ];
    }

    $enrollments = [];
    foreach ($db->query('SELECT * FROM enrollments ORDER BY id ASC') as $row) {
        $enrollments[] = [
            'id' => (int) $row['id'],
            'studentId' => (int) $row['student_id'],
            'courseId' => (int) $row['course_id'],
            'classId' => $row['class_id'] === null ? null : (int) $row['class_id'],
            'enrolledBy' => $row['enrolled_by'],
            'enrolledAt' => elips_date_only($row['enrolled_at']),
            'status' => $row['status'],
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $classSessions = [];
    foreach ($db->query('SELECT id FROM class_sessions ORDER BY class_id ASC, session_number ASC, id ASC') as $row) {
        $session = elips_session_public((int) $row['id']);
        if ($session) $classSessions[] = $session;
    }

    $attendances = [];
    foreach ($db->query('SELECT id FROM attendances ORDER BY session_id ASC, enrollment_id ASC, id ASC') as $row) {
        $attendance = elips_attendance_public((int) $row['id']);
        if ($attendance) $attendances[] = $attendance;
    }

    $attendanceCorrections = [];
    foreach ($db->query('SELECT id FROM attendance_corrections ORDER BY id ASC') as $row) {
        $correction = elips_attendance_correction_public((int) $row['id']);
        if ($correction) $attendanceCorrections[] = $correction;
    }

    $grades = [];
    foreach ($db->query('SELECT id FROM grades ORDER BY id ASC') as $row) {
        $grade = elips_grade_public((int) $row['id']);
        if ($grade) $grades[] = $grade;
    }
    $predicateRanges = [];
    foreach ($db->query('SELECT * FROM predicate_ranges ORDER BY sort_order ASC, min_score DESC, id ASC') as $row) {
        $predicateRanges[] = [
            'id' => (int) $row['id'],
            'letter' => $row['letter'],
            'label' => $row['label'],
            'minScore' => (float) $row['min_score'],
            'maxScore' => (float) $row['max_score'],
            'sortOrder' => (int) $row['sort_order'],
            'isActive' => (bool) $row['is_active'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }

    $gradeSchemes = [];
    foreach ($db->query('SELECT id FROM grade_schemes ORDER BY id ASC') as $row) {
        $scheme = elips_grade_scheme_row((int) $row['id']);
        if ($scheme) {
            $gradeSchemes[] = elips_grade_scheme_public($scheme);
        }
    }

    $gradeSchemeComponents = [];
    foreach ($db->query('SELECT * FROM grade_scheme_components ORDER BY scheme_id ASC, sort_order ASC, id ASC') as $row) {
        $gradeSchemeComponents[] = elips_grade_scheme_component_public($row);
    }

    $gradeComponentScores = [];
    foreach ($db->query('SELECT * FROM grade_component_scores ORDER BY id ASC') as $row) {
        $gradeComponentScores[] = elips_grade_component_score_public($row);
    }

    $gradeCorrections = [];
    foreach ($db->query(
        'SELECT gc.*, u.name AS actor_name FROM grade_corrections gc
         LEFT JOIN users u ON u.id = gc.actor_id
         ORDER BY gc.id ASC'
    ) as $row) {
        $gradeCorrections[] = elips_grade_correction_public($row);
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

    $classMaterialSettings = [];
    foreach ($db->query('SELECT id FROM classes ORDER BY id ASC') as $row) {
        $classMaterialSettings[] = elips_class_material_settings_public((int) $row['id']);
    }

    // Metadata materi tambahan saja: konten dan path file privat tidak
    // pernah ikut snapshot. Konten hanya dikirim melalui material.classroom
    // setelah aturan akses diverifikasi.
    $classMaterials = [];
    foreach ($db->query('SELECT id FROM class_materials ORDER BY class_id ASC, sort_order ASC, id ASC') as $row) {
        $material = elips_class_material_row((int) $row['id']);
        if ($material) {
            $classMaterials[] = elips_class_material_public($material, false);
        }
    }

    $classLessonProgress = [];
    foreach ($db->query('SELECT * FROM class_lesson_progress ORDER BY id ASC') as $row) {
        $classLessonProgress[] = elips_class_lesson_progress_public($row);
    }

    $classMaterialProgress = [];
    foreach ($db->query('SELECT * FROM class_material_progress ORDER BY id ASC') as $row) {
        $classMaterialProgress[] = elips_class_material_progress_public($row);
    }

    // Tugas, pengumpulan, dan ujian. Snapshot hanya memuat metadata dan
    // status/nilai. Jawaban teks, umpan balik, dan path file privat tidak
    // pernah ikut; konten lengkap hanya lewat aksi yang memeriksa akses.
    $assignments = [];
    foreach ($db->query('SELECT id FROM assignments ORDER BY id ASC') as $row) {
        $assignment = elips_assignment_row((int) $row['id']);
        if ($assignment) {
            $assignments[] = elips_assignment_public($assignment, false);
        }
    }

    $assignmentFiles = [];
    foreach ($db->query('SELECT * FROM assignment_files ORDER BY assignment_id ASC, id ASC') as $row) {
        $assignmentFiles[] = elips_assignment_file_public($row, false);
    }

    $assignmentRubrics = [];
    foreach ($db->query('SELECT * FROM assignment_rubrics ORDER BY assignment_id ASC, sort_order ASC, id ASC') as $row) {
        $assignmentRubrics[] = elips_assignment_rubric_public($row);
    }

    $submissions = [];
    foreach ($db->query('SELECT id FROM submissions ORDER BY id ASC') as $row) {
        $submission = elips_submission_row((int) $row['id']);
        if ($submission) {
            $submissions[] = elips_submission_public($submission, false);
        }
    }

    $submissionFiles = [];
    foreach ($db->query('SELECT * FROM submission_files ORDER BY submission_id ASC, id ASC') as $row) {
        $submissionFiles[] = elips_submission_file_public($row, false);
    }

    $submissionReviews = [];
    $reviewRows = $db->query(
        'SELECT r.*, u.name AS reviewer_name
         FROM submission_reviews r
         LEFT JOIN users u ON u.id = r.reviewer_id
         ORDER BY r.submission_id ASC, r.id ASC'
    );
    foreach ($reviewRows as $row) {
        $submissionReviews[] = elips_submission_review_public($row);
    }

    $exams = [];
    foreach ($db->query('SELECT id FROM exams ORDER BY id ASC') as $row) {
        $exam = elips_exam_row((int) $row['id']);
        if ($exam) {
            $exams[] = elips_exam_public($exam, false);
        }
    }

    $examParticipants = [];
    $participantRows = $db->query(
        'SELECT p.*, u.name AS student_name
         FROM exam_participants p
         JOIN users u ON u.id = p.student_id
         ORDER BY p.exam_id ASC, u.name ASC, p.id ASC'
    );
    foreach ($participantRows as $row) {
        $examParticipants[] = elips_exam_participant_public($row);
    }

    $examResults = [];
    $resultRows = $db->query(
        'SELECT r.*, u.name AS student_name, g.name AS graded_by_name
         FROM exam_results r
         JOIN users u ON u.id = r.student_id
         LEFT JOIN users g ON g.id = r.graded_by
         ORDER BY r.exam_id ASC, u.name ASC, r.id ASC'
    );
    foreach ($resultRows as $row) {
        $examResults[] = elips_exam_result_public($row);
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
        'branches' => $branches,
        'categories' => $categories,
        'courses' => $courses,
        'curriculumVersions' => $curriculumVersions,
        'classes' => $classes,
        'classInstructorHistories' => $classInstructorHistories,
        'enrollments' => $enrollments,
        'classSessions' => $classSessions,
        'attendances' => $attendances,
        'attendanceCorrections' => $attendanceCorrections,
        'modules' => $modules,
        'lessons' => $lessons,
        'grades' => $grades,
        'predicateRanges' => $predicateRanges,
        'gradeSchemes' => $gradeSchemes,
        'gradeSchemeComponents' => $gradeSchemeComponents,
        'gradeComponentScores' => $gradeComponentScores,
        'gradeCorrections' => $gradeCorrections,
        'certificates' => $certificates,
        'completedLessons' => $completedLessons,
        'classMaterialSettings' => $classMaterialSettings,
        'classMaterials' => $classMaterials,
        'classLessonProgress' => $classLessonProgress,
        'classMaterialProgress' => $classMaterialProgress,
        'assignments' => $assignments,
        'assignmentFiles' => $assignmentFiles,
        'assignmentRubrics' => $assignmentRubrics,
        'submissions' => $submissions,
        'submissionFiles' => $submissionFiles,
        'submissionReviews' => $submissionReviews,
        'exams' => $exams,
        'examParticipants' => $examParticipants,
        'examResults' => $examResults,
        'auditLogs' => $auditLogs,
        'notifications' => $notifications,
    ];
}
