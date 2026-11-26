<?php
// Direktori: employee/operasional/penerbitan-sertifikat.php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/certificate-issue',
    'roles' => ['admin', 'karyawan'],
    'divisions' => ['operasional'],
    'title' => 'Penerbitan Sertifikat - Operasional - ELIPS LMS',
    'active' => 'employee/operasional/penerbitan-sertifikat.php',
    'css' => ['admin-registrations', 'academic', 'certificate-issue'],
    'scripts' => ['ui', 'employee/shared/common'],
    'navbar' => 'employee-operasional',
    'sidebar' => 'employee-operasional',
];
layout('app', $page);
include __DIR__ . '/../../partials/academic/certificate-issue.php';
?>
        <button class="dashboard-sidebar-toggle" id="opsSidebarToggle" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button>
        <div class="dashboard-sidebar-backdrop" id="opsSidebarBackdrop"></div>
<?php end_layout(); ?>