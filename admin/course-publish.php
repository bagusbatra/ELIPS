<?php
// Direktori: admin/course-publish.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/course-publish',
    'roles' => ['admin'],
    'title' => 'Publikasi Kursus - ELIPS LMS',
    'area' => 'admin',
    'active' => 'admin/courses.php',
    'css' => ['admin-registrations', 'academic', 'course-publish'],
    'scripts' => ['ui', 'employee/shared/common'],
    'sidebar' => 'admin',
];
$cpBackUrl = url('admin/courses.php');
layout('app', $page);
include __DIR__ . '/../partials/academic/course-publish.php';
end_layout();