<?php
// Direktori: admin/payments.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'finance/payments',
    'roles' => ['admin'],
    'title' => 'Pembayaran Siswa - ELIPS LMS',
    'area' => 'admin',
    'css' => ['admin-registrations', 'finance'],
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
include __DIR__ . '/../partials/finance/payments.php';
end_layout();