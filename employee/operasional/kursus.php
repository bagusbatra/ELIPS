<?php
// Direktori: employee/operasional/kursus.php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/courses',
    'roles' => ['admin', 'karyawan'],
    'divisions' => ['operasional'],
    'title' => 'Kelola Kursus - Operasional - ELIPS LMS',
    'css' => ['admin-registrations', 'academic'],
    'scripts' => ['@crud', 'ui', 'employee/shared/common'],
    'navbar' => 'employee-operasional',
    'sidebar' => 'employee-operasional',
];
layout('app', $page);
include __DIR__ . '/../../partials/academic/courses.php';
?>
        <button class="dashboard-sidebar-toggle" id="opsSidebarToggle" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button>
        <div class="dashboard-sidebar-backdrop" id="opsSidebarBackdrop"></div>
<?php end_layout(); ?>