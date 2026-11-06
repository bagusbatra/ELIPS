-- ============================================================
-- ELIPS LMS - skema database MySQL 8 (v4.0 Phase 1-3 + cabang)
-- Jalankan melalui api/install.php agar data demo ikut terisi.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `elips_lms`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `elips_lms`;

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'instruktur', 'peserta') NOT NULL DEFAULT 'peserta',
    `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `created_at` DATE NOT NULL DEFAULT (CURRENT_DATE),
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL DEFAULT '',
    `city` VARCHAR(100) NOT NULL DEFAULT '',
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `manager` VARCHAR(150) NOT NULL DEFAULT '',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `notes` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_branches_code` (`code`),
    KEY `idx_branches_status` (`status`),
    KEY `idx_branches_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `description` TEXT NOT NULL,
    `instructor_id` INT UNSIGNED NULL,
    `status` ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    `created_at` DATE NOT NULL DEFAULT (CURRENT_DATE),
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_courses_instructor` (`instructor_id`),
    KEY `idx_courses_status` (`status`),
    KEY `idx_courses_category` (`category_id`),
    CONSTRAINT `fk_courses_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_courses_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `curriculum_versions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('draft', 'published', 'locked', 'retired') NOT NULL DEFAULT 'draft',
    `effective_date` DATE NULL,
    `notes` TEXT NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_curriculum_course_code` (`course_id`, `code`),
    KEY `idx_curriculum_course_status` (`course_id`, `status`),
    KEY `idx_curriculum_status` (`status`),
    CONSTRAINT `fk_curriculum_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_curriculum_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_curriculum_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id` INT UNSIGNED NOT NULL,
    `curriculum_version_id` INT UNSIGNED NOT NULL,
    `instructor_id` INT UNSIGNED NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_modules_course` (`course_id`, `sort_order`),
    KEY `idx_modules_version` (`curriculum_version_id`, `sort_order`),
    KEY `idx_modules_instructor` (`instructor_id`),
    CONSTRAINT `fk_modules_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_modules_version` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_modules_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lessons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_id` INT UNSIGNED NOT NULL,
    `course_id` INT UNSIGNED NOT NULL,
    `curriculum_version_id` INT UNSIGNED NOT NULL,
    `instructor_id` INT UNSIGNED NULL,
    `title` VARCHAR(200) NOT NULL,
    `type` ENUM('text', 'video', 'file', 'link') NOT NULL DEFAULT 'text',
    `content` LONGTEXT NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_lessons_module` (`module_id`, `sort_order`),
    KEY `idx_lessons_course` (`course_id`),
    KEY `idx_lessons_version` (`curriculum_version_id`, `sort_order`),
    KEY `idx_lessons_instructor` (`instructor_id`),
    CONSTRAINT `fk_lessons_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lessons_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lessons_version` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lessons_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `course_id` INT UNSIGNED NOT NULL,
    `curriculum_version_id` INT UNSIGNED NOT NULL,
    `branch_id` INT UNSIGNED NULL,
    `instructor_id` INT UNSIGNED NULL,
    `period_start` DATE NULL,
    `period_end` DATE NULL,
    `quota` INT UNSIGNED NOT NULL DEFAULT 0,
    `location` VARCHAR(150) NOT NULL DEFAULT '',
    `room` VARCHAR(100) NOT NULL DEFAULT '',
    `schedule_note` VARCHAR(255) NOT NULL DEFAULT '',
    `planned_jp` DECIMAL(6, 2) NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'open', 'ready', 'ongoing', 'completed', 'cancelled', 'archived') NOT NULL DEFAULT 'draft',
    `legacy_import` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_classes_code` (`code`),
    KEY `idx_classes_course` (`course_id`),
    KEY `idx_classes_version` (`curriculum_version_id`),
    KEY `idx_classes_branch` (`branch_id`),
    KEY `idx_classes_instructor` (`instructor_id`),
    KEY `idx_classes_status` (`status`),
    CONSTRAINT `fk_classes_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_classes_version` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_classes_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_classes_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_classes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_classes_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `enrollments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `course_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NULL,
    `enrolled_by` VARCHAR(50) NOT NULL DEFAULT 'admin',
    `enrolled_at` DATE NOT NULL DEFAULT (CURRENT_DATE),
    `status` ENUM('pending', 'active', 'completed', 'withdrawn', 'suspended', 'cancelled', 'dihentikan') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enrollments_student_class` (`student_id`, `class_id`),
    KEY `idx_enrollments_student_course` (`student_id`, `course_id`),
    KEY `idx_enrollments_course` (`course_id`),
    KEY `idx_enrollments_class` (`class_id`),
    CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_instructor_histories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` INT UNSIGNED NOT NULL,
    `instructor_id` INT UNSIGNED NULL,
    `started_at` DATE NOT NULL,
    `ended_at` DATE NULL,
    `reason` TEXT NOT NULL,
    `actor_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_class_history_class` (`class_id`, `started_at`),
    KEY `idx_class_history_instructor` (`instructor_id`),
    CONSTRAINT `fk_class_history_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_history_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_history_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grades` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enrollment_id` INT UNSIGNED NULL,
    `course_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `instructor_id` INT UNSIGNED NULL,
    `score` TINYINT UNSIGNED NOT NULL,
    `grade` CHAR(2) NOT NULL,
    `note` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_grades_enrollment` (`enrollment_id`),
    KEY `idx_grades_student_course` (`student_id`, `course_id`),
    KEY `idx_grades_instructor` (`instructor_id`),
    CONSTRAINT `fk_grades_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_grades_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_grades_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_grades_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `certificates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `course_id` INT UNSIGNED NOT NULL,
    `enrollment_id` INT UNSIGNED NULL,
    `certificate_number` VARCHAR(80) NOT NULL,
    `status` ENUM('draft', 'publish') NOT NULL DEFAULT 'draft',
    `grade` CHAR(2) NULL,
    `score` TINYINT UNSIGNED NULL,
    `issued_by_instructor_at` DATETIME NULL,
    `approved_by_admin_id` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_certificates_number` (`certificate_number`),
    UNIQUE KEY `uq_certificates_enrollment` (`enrollment_id`),
    KEY `idx_certificates_student_course` (`student_id`, `course_id`),
    KEY `idx_certificates_course` (`course_id`),
    KEY `idx_certificates_status` (`status`),
    CONSTRAINT `fk_certificates_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_certificates_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_certificates_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_certificates_admin` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `completed_lessons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `course_id` INT UNSIGNED NOT NULL,
    `lesson_id` INT UNSIGNED NOT NULL,
    `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_completed_student_lesson` (`student_id`, `lesson_id`),
    KEY `idx_completed_course` (`course_id`),
    CONSTRAINT `fk_completed_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_completed_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_completed_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(30) NOT NULL,
    `entity` VARCHAR(30) NOT NULL,
    `entity_id` INT UNSIGNED NULL,
    `entity_label` VARCHAR(255) NOT NULL DEFAULT '',
    `actor_id` INT UNSIGNED NULL,
    `actor_name` VARCHAR(150) NOT NULL DEFAULT 'Sistem',
    `actor_role` VARCHAR(30) NOT NULL DEFAULT 'sistem',
    `detail` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_created` (`created_at`),
    KEY `idx_audit_actor` (`actor_id`),
    KEY `idx_audit_entity` (`entity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_sessions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` INT UNSIGNED NOT NULL,
    `session_number` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL DEFAULT '',
    `module_id` INT UNSIGNED NULL,
    `session_date` DATE NOT NULL,
    `start_time` TIME NULL,
    `end_time` TIME NULL,
    `location` VARCHAR(150) NOT NULL DEFAULT '',
    `room` VARCHAR(100) NOT NULL DEFAULT '',
    `instructor_id` INT UNSIGNED NULL,
    `planned_jp` DECIMAL(6, 2) NOT NULL DEFAULT 0,
    `actual_jp` DECIMAL(6, 2) NOT NULL DEFAULT 0,
    `status` ENUM('scheduled', 'confirmed', 'completed', 'rescheduled', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled',
    `rescheduled_from_id` INT UNSIGNED NULL,
    `notes` TEXT NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_sessions_number` (`class_id`, `session_number`),
    KEY `idx_class_sessions_class_date` (`class_id`, `session_date`),
    KEY `idx_class_sessions_status` (`status`),
    KEY `idx_class_sessions_module` (`module_id`),
    KEY `idx_class_sessions_instructor` (`instructor_id`),
    KEY `idx_class_sessions_replaced` (`rescheduled_from_id`),
    CONSTRAINT `fk_class_sessions_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_sessions_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_sessions_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_sessions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_sessions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_sessions_replaced` FOREIGN KEY (`rescheduled_from_id`) REFERENCES `class_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendances` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` INT UNSIGNED NOT NULL,
    `enrollment_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `status` ENUM('present', 'late', 'permission', 'sick', 'absent', 'excused') NOT NULL DEFAULT 'absent',
    `recorded_by` INT UNSIGNED NULL,
    `recorded_at` DATETIME NULL,
    `note` TEXT NOT NULL,
    `corrected_at` DATETIME NULL,
    `correction_reason` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendances_session_enrollment` (`session_id`, `enrollment_id`),
    KEY `idx_attendances_student` (`student_id`),
    KEY `idx_attendances_enrollment` (`enrollment_id`),
    KEY `idx_attendances_status` (`status`),
    CONSTRAINT `fk_attendances_session` FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendances_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendances_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendances_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_corrections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attendance_id` INT UNSIGNED NOT NULL,
    `session_id` INT UNSIGNED NOT NULL,
    `enrollment_id` INT UNSIGNED NOT NULL,
    `old_status` VARCHAR(20) NOT NULL DEFAULT '',
    `new_status` VARCHAR(20) NOT NULL,
    `actor_id` INT UNSIGNED NULL,
    `actor_name` VARCHAR(150) NOT NULL DEFAULT 'Sistem',
    `actor_role` VARCHAR(30) NOT NULL DEFAULT 'sistem',
    `reason` TEXT NOT NULL,
    `corrected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attendance_corrections_attendance` (`attendance_id`),
    KEY `idx_attendance_corrections_session` (`session_id`),
    KEY `idx_attendance_corrections_enrollment` (`enrollment_id`),
    CONSTRAINT `fk_attendance_corrections_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_corrections_session` FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_corrections_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_corrections_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_material_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` INT UNSIGNED NOT NULL,
    `access_mode` ENUM('open', 'sequential', 'scheduled', 'conditional') NOT NULL DEFAULT 'open',
    `require_instructor_approval` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT NOT NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_material_settings_class` (`class_id`),
    CONSTRAINT `fk_class_material_settings_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_material_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_materials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` INT UNSIGNED NOT NULL,
    `lesson_id` INT UNSIGNED NULL,
    `session_id` INT UNSIGNED NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `type` ENUM('text', 'document', 'video', 'link', 'file') NOT NULL DEFAULT 'text',
    `content` LONGTEXT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL DEFAULT '',
    `file_path` VARCHAR(500) NOT NULL DEFAULT '',
    `file_size` BIGINT UNSIGNED NULL,
    `mime_type` VARCHAR(120) NOT NULL DEFAULT '',
    `access_level` ENUM('required', 'optional', 'reference') NOT NULL DEFAULT 'optional',
    `available_from` DATE NULL,
    `status` ENUM('active', 'hidden') NOT NULL DEFAULT 'active',
    `reason` TEXT NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_class_materials_class_status` (`class_id`, `status`, `sort_order`),
    KEY `idx_class_materials_lesson` (`lesson_id`),
    KEY `idx_class_materials_session` (`session_id`),
    KEY `idx_class_materials_created_by` (`created_by`),
    CONSTRAINT `fk_class_materials_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_materials_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_materials_session` FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_class_materials_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_lesson_progress` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` INT UNSIGNED NOT NULL,
    `enrollment_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `lesson_id` INT UNSIGNED NOT NULL,
    `completed_by` INT UNSIGNED NULL,
    `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_lesson_progress_enrollment_lesson` (`enrollment_id`, `lesson_id`),
    KEY `idx_class_lesson_progress_class` (`class_id`),
    KEY `idx_class_lesson_progress_student` (`student_id`),
    KEY `idx_class_lesson_progress_lesson` (`lesson_id`),
    CONSTRAINT `fk_class_lesson_progress_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_lesson_progress_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_lesson_progress_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_lesson_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_lesson_progress_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_material_progress` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` INT UNSIGNED NOT NULL,
    `enrollment_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `material_id` INT UNSIGNED NOT NULL,
    `completed_by` INT UNSIGNED NULL,
    `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_class_material_progress_enrollment_material` (`enrollment_id`, `material_id`),
    KEY `idx_class_material_progress_class` (`class_id`),
    KEY `idx_class_material_progress_student` (`student_id`),
    KEY `idx_class_material_progress_material` (`material_id`),
    CONSTRAINT `fk_class_material_progress_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_material_progress_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_material_progress_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_material_progress_material` FOREIGN KEY (`material_id`) REFERENCES `class_materials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_material_progress_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(40) NOT NULL,
    `channel` VARCHAR(20) NOT NULL DEFAULT 'email',
    `simulated` TINYINT(1) NOT NULL DEFAULT 1,
    `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
    `recipient_id` INT UNSIGNED NULL,
    `recipient_name` VARCHAR(150) NOT NULL DEFAULT '',
    `recipient_email` VARCHAR(190) NOT NULL DEFAULT '',
    `recipient_role` VARCHAR(30) NOT NULL DEFAULT 'peserta',
    `subject` VARCHAR(255) NOT NULL DEFAULT '',
    `body` TEXT NOT NULL,
    `entity` VARCHAR(30) NOT NULL DEFAULT '',
    `entity_id` INT UNSIGNED NULL,
    `entity_label` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_created` (`created_at`),
    KEY `idx_notifications_type` (`type`),
    KEY `idx_notifications_recipient` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
