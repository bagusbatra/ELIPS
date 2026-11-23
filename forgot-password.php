<?php
// Direktori: forgot-password.php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'forgot-password',
    'title' => 'Lupa Sandi - ELIPS LMS',
    'body_class' => 'auth-page',
    'css' => ['auth', 'activation'],
    'scripts' => ['@crud', 'ui'],
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
                <span class="auth-eyebrow">PULIHKAN AKSES</span>
                <h1>Lupa sandi? Admin akan membantumu.</h1>
                <p>Kirim permintaan reset. Admin akan mengirim link pembuatan sandi baru ke WhatsApp-mu.</p>
            </div>
        </aside>
        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-card-head">
                    <span class="auth-mark" aria-hidden="true"><i class="bi bi-key"></i></span>
                    <h2>Lupa sandi</h2>
                    <p>Masukkan email atau nomor telepon akun siswamu.</p>
                </div>
                <form class="auth-form" id="forgotForm" novalidate>
                    <p class="auth-error" id="forgotError" role="alert" hidden></p>
                    <div class="auth-field">
                        <label for="identifier">Email atau No. telepon</label>
                        <div class="auth-input">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <input type="text" id="identifier" class="form-input" autocomplete="username"
                                   autocapitalize="none" spellcheck="false" required>
                        </div>
                    </div>
                    <button type="submit" class="auth-submit">Kirim permintaan <i class="bi bi-send" aria-hidden="true"></i></button>
                </form>
                <div class="act-state is-ok" id="forgotDone" hidden>
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    <p id="forgotDoneText"></p>
                </div>
                <p class="auth-signup"><a href="<?= url('login.php') ?>">Kembali ke halaman masuk</a></p>
            </div>
        </main>
    </div>
<?php end_layout(); ?>