<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'learning',
    'roles' => ['admin', 'instruktur', 'peserta'],
    'title' => 'Belajar - ELIPS LMS',
    'body_class' => 'learning-body',
    'scripts' => ['@crud', 'crud-academic', 'crud-tasks', 'ui'],
];
layout('page', $page);
?>
    <header class="learning-header">
        <div class="learning-header-container">
            <div class="learning-header-left">
                <a href="<?= url('student/dashboard.php') ?>" class="learning-back" id="learningBackLink">
                    <i class="bi bi-arrow-left"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
                <div class="learning-course-title" id="learningCourseTitle">Memuat kursus...</div>
            </div>
            <div class="learning-header-right">
                <div class="learning-progress">
                    <span class="learning-progress-text" id="learningProgressText">Progres: 0/0 Pelajaran (0%)</span>
                    <div class="learning-progress-bar">
                        <div class="learning-progress-fill" id="learningProgressFill" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="learning-layout" id="learningLayout">
        <aside class="learning-sidebar" id="learningSidebar">
            <div class="learning-sidebar-header">
                <h3 class="learning-sidebar-title">Daftar Materi</h3>
            </div>
            <div class="learning-modules" id="learningModules"></div>
        </aside>

        <main class="learning-main">
            <button class="learning-sidebar-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="learning-content-container" id="learningContent"></div>
        </main>
    </div>
<?php end_layout(); ?>
