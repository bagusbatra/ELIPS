<?php
// Direktori: employee/keuangan/payments.php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'finance/payments',
    'roles' => ['admin', 'karyawan'],
    'divisions' => ['keuangan'],
    'title' => 'Pembayaran Siswa - ELIPS LMS',
    'css' => ['admin-registrations', 'finance'],
    'scripts' => ['@crud', 'ui', 'employee/shared/common', 'employee/keuangan/dashboard'],
    'navbar' => 'employee-keuangan',
    'sidebar' => 'employee-keuangan',
];
layout('app', $page);
include __DIR__ . '/../../partials/finance/payments.php';
end_layout();