<?php
// Direktori: admin/academic.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/academic',
    'roles' => ['admin'],
    'title' => 'Kurikulum &amp; Kelas - ELIPS LMS',
    'area' => 'admin',
    'body_class' => 'page-academic',
    'css' => ['academic'],
    'scripts' => ['@crud', 'crud-academic', 'ui', 'employee/shared/common'],
    'sidebar' => 'admin',
];
layout('app', $page);
include __DIR__ . '/../partials/academic/academic.php';
end_layout();