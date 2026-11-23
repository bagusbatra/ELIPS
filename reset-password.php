<?php
// Direktori: reset-password.php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'reset-password',
    'title' => 'Buat Sandi Baru - ELIPS LMS',
    'body_class' => 'auth-page',
    'css' => ['auth', 'activation'],
    'scripts' => ['@crud', 'ui', 'password-rules'],
];
layout('page', $page);
?>
    <div class="auth-shell">
        <aside class="auth-aside">
            <a href="<?= url('index.php') ?>" class="auth-brand">
                <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                <span>ELIPS LMS</span>
            </a>
            <div class="auth-aside-copy">
                <span class="auth-eyebrow">KEAMANAN AKUN</span>
                <h1>Buat sandi baru yang kuat.</h1>
                <p>Setelah sandi diganti, semua perangkat yang sedang masuk akan dikeluarkan.</p>
            </div>
        </aside>
        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-card-head">
                    <span class="auth-mark" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                    <h2>Sandi baru</h2>
                    <p id="resetIntro">Memeriksa link…</p>
                </div>
                <div class="act-state" id="resetError" hidden>
                    <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                    <p id="resetErrorText"></p>
                    <p><a href="<?= url('forgot-password.php') ?>">Minta link baru</a></p>
                </div>
                <div class="act-state is-ok" id="resetDone" hidden>
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    <p>Sandi berhasil diubah.</p>
                    <p><a class="auth-submit" href="<?= url('login.php') ?>">Masuk sekarang</a></p>
                </div>
                <form class="auth-form" id="resetForm" novalidate hidden>
                    <p class="auth-error" id="resetFormError" role="alert" hidden></p>
                    <div class="auth-field">
                        <label for="password">Sandi baru</label>
                        <div class="auth-input">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" id="password" class="form-input" autocomplete="new-password" required>
                            <button class="auth-toggle-pass" type="button" data-toggle-password="password" aria-label="Tampilkan sandi" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <ul class="pw-rules" id="pwRules" aria-live="polite"></ul>
                    <div class="auth-field">
                        <label for="passwordConfirm">Ulangi sandi baru</label>
                        <div class="auth-input">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" id="passwordConfirm" class="form-input" autocomplete="new-password" required>
                            <button class="auth-toggle-pass" type="button" data-toggle-password="passwordConfirm" aria-label="Tampilkan konfirmasi sandi" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="auth-submit">Simpan sandi baru <i class="bi bi-check2" aria-hidden="true"></i></button>
                </form>
            </div>
        </main>
    </div>
<?php end_layout(); ?>