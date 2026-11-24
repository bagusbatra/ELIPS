<?php
// Direktori: employee/operasional/kursus-publikasi.php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/course-publish',
    'roles' => ['admin', 'karyawan'],
    'divisions' => ['operasional'],
    'title' => 'Publikasi Kursus - Operasional - ELIPS LMS',
    'active' => 'employee/operasional/kursus.php',
    'css' => ['admin-registrations', 'academic', 'course-publish'],
    'scripts' => ['ui', 'employee/shared/common'],
    'navbar' => 'employee-operasional',
    'sidebar' => 'employee-operasional',
];
$cpBackUrl = url('employee/operasional/kursus.php');
layout('app', $page);
include __DIR__ . '/../../partials/academic/course-publish.php';
?>
        <button class="dashboard-sidebar-toggle" id="opsSidebarToggle" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button>
        <div class="dashboard-sidebar-backdrop" id="opsSidebarBackdrop"></div>
<?php end_layout(); ?>