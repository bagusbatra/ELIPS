<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'title' => 'Masuk - ELIPS LMS',
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
                <h1 class="auth-title">Selamat Datang Kembali</h1>
                <p class="auth-subtitle">Masuk untuk melanjutkan pembelajaran</p>
            </div>
            <div class="auth-body">
                <form class="auth-form" id="loginForm" action="#" method="get">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" class="form-input" placeholder="nama@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" class="form-input" placeholder="Masukkan password" required>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                        <input type="checkbox" id="remember" checked style="width: 1rem; height: 1rem; accent-color: var(--primary);">
                        <label for="remember" style="font-size: 0.875rem; color: var(--text-secondary); cursor: pointer;">Ingat saya di perangkat ini</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Masuk
                    </button>
                </form>
            </div>
            <div class="auth-footer">
                Belum punya akun? <a href="<?= url('register.php') ?>">Daftar sekarang</a>
            </div>
            <div style="padding: 1rem 2rem 1.5rem; border-top: 1px solid var(--border); font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.7;">
                <strong>Akun demo:</strong><br>
                Admin: admin@example.com<br>
                Instruktur: ahmad@example.com / siti@example.com<br>
                Peserta: budi.student@example.com / ani@example.com<br>
                <span style="font-size: 0.75rem;">(password semua akun demo: <strong>elips123</strong>)</span>
            </div>
        </div>
    </div>
<?php end_layout(); ?>
