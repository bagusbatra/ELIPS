<?php
// Direktori: register.php
// Pendaftaran akun langsung sudah ditutup. Calon siswa memilih program di
// katalog lalu mengisi formulir pendaftaran; akun dibuat admin lewat link aktivasi.
require_once __DIR__ . '/includes/bootstrap.php';
header('Location: ' . url('index.php#pelatihan'), true, 302);
exit;