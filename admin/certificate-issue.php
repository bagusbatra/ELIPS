<?php
// Direktori: admin/certificate-issue.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/certificate-issue',
    'roles' => ['admin'],
    'title' => 'Penerbitan Sertifikat - ELIPS LMS',
    'area' => 'admin',
    'active' => 'admin/certificate-issue.php',
    'css' => ['admin-registrations', 'academic', 'certificate-issue'],
    'scripts' => ['ui', 'employee/shared/common'],
    'sidebar' => 'admin',
];
layout('app', $page);
include __DIR__ . '/../partials/academic/certificate-issue.php';
end_layout();