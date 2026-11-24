<?php
// Direktori: employee/operasional/akademik.php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/academic',
    'roles' => ['admin', 'karyawan'],
    'divisions' => ['operasional'],
    'title' => 'Kurikulum &amp; Kelas - Operasional - ELIPS LMS',
    'body_class' => 'page-academic',
    'css' => ['academic'],
    'scripts' => ['@crud', 'crud-academic', 'ui', 'employee/shared/common'],
    'navbar' => 'employee-operasional',
    'sidebar' => 'employee-operasional',
];
layout('app', $page);
include __DIR__ . '/../../partials/academic/academic.php';
?>
        <button class="dashboard-sidebar-toggle" id="opsSidebarToggle" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button>
        <div class="dashboard-sidebar-backdrop" id="opsSidebarBackdrop"></div>
<?php end_layout(); ?>