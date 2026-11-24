<?php
// Direktori: admin/courses.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/courses',
    'roles' => ['admin'],
    'title' => 'Kelola Kursus - ELIPS LMS',
    'area' => 'admin',
    'css' => ['admin-registrations', 'academic'],
    'scripts' => ['@crud', 'ui', 'employee/shared/common'],
    'sidebar' => 'admin',
];
layout('app', $page);
include __DIR__ . '/../partials/academic/courses.php';
end_layout();