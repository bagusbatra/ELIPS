<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'register',
    'title' => 'Daftar - ELIPS LMS',
    'scripts' => ['@crud', 'ui'],
];
layout('page', $page);
?>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="<?= url('index.php') ?>" class="auth-logo">
                    <i class="bi bi-mortarboard-fill"></i>
                    <span>ELIPS LMS</span>
                </a>
                <h1 class="auth-title">Buat Akun Baru</h1>
                <p class="auth-subtitle">Daftar untuk mulai mengikuti pelatihan</p>
            </div>
            <div class="auth-body">
                <form class="auth-form" id="registerForm" action="<?= url('login.php') ?>" method="get">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="firstName">Nama Depan</label>
                            <input type="text" id="firstName" class="form-input" placeholder="Budi" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="lastName">Nama Belakang</label>
                            <input type="text" id="lastName" class="form-input" placeholder="Santoso" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" class="form-input" placeholder="nama@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">No. Telepon</label>
                        <input type="tel" id="phone" class="form-input" placeholder="08xxxxxxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" class="form-input" placeholder="Minimal 8 karakter" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="passwordConfirm">Konfirmasi Password</label>
                        <input type="password" id="passwordConfirm" class="form-input" placeholder="Ulangi password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i class="bi bi-person-plus"></i>
                        Daftar
                    </button>
                </form>
            </div>
            <div class="auth-footer">
                Sudah punya akun? <a href="<?= url('login.php') ?>">Masuk disini</a>
            </div>
        </div>
    </div>
<?php end_layout(); ?>
