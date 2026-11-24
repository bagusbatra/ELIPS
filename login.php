<?php
// Direktori: login.php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'title' => 'Masuk - ELIPS LMS',
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
                <span class="auth-eyebrow">BELAJAR LEBIH TERARAH</span>
                <h1>Satu tempat untuk pelatihan, progres, dan sertifikatmu.</h1>
                <p>Lanjutkan perjalanan belajarmu. Jadwal, materi, dan sertifikat tersimpan rapi di sini.</p>
                <ul class="auth-highlights">
                    <li><i class="bi bi-calendar-check" aria-hidden="true"></i>Jadwal pelatihan yang jelas</li>
                    <li><i class="bi bi-graph-up-arrow" aria-hidden="true"></i>Progres belajar terpantau</li>
                    <li><i class="bi bi-patch-check" aria-hidden="true"></i>Sertifikat yang bisa diverifikasi</li>
                </ul>
            </div>

            <div class="auth-aside-foot">
                <i class="bi bi-quote" aria-hidden="true"></i>
                <span>Platform pelatihan dan kursus bersertifikat untuk langkah karier berikutnya.</span>
            </div>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-card-head">
                    <span class="auth-mark" aria-hidden="true"><i class="bi bi-box-arrow-in-right"></i></span>
                    <h2>Selamat datang kembali</h2>
                    <p>Masuk untuk melanjutkan pembelajaran.</p>
                </div>

                <form class="auth-form" id="loginForm" action="<?= url('login.php') ?>" method="post">
                    <p class="auth-error" id="loginError" role="alert" hidden></p>

                    <div class="auth-field">
                        <label for="email">Email atau No. telepon</label>
                        <div class="auth-input">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <input type="text" id="email" class="form-input" name="email"
                                   placeholder="nama@example.com atau 08xxxxxxxxxx" autocomplete="username"
                                   inputmode="email" autocapitalize="none" spellcheck="false" required>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="password">Password</label>
                        <div class="auth-input">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" id="password" class="form-input" name="password"
                                   placeholder="Masukkan password" autocomplete="current-password" required>
                            <button class="auth-toggle-pass" type="button" data-toggle-password
                                    aria-label="Tampilkan password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <p class="auth-forgot"><a href="<?= url('forgot-password.php') ?>">Lupa sandi?</a></p>

                    <label class="auth-remember" for="remember">
                        <input type="checkbox" id="remember" name="remember" checked>
                        <span class="auth-checkbox" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <button type="submit" class="auth-submit">
                        Masuk
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="auth-divider"><span>atau</span></div>
                <p class="auth-signup">Belum punya akun? <a href="<?= url('index.php#pelatihan') ?>">Pilih program &amp; daftar</a></p>

                <?php if (strtolower((string) getenv('ELIPS_ENV')) !== 'production'): ?>
                <details class="auth-demo">
                    <summary>
                        <span><i class="bi bi-lightbulb" aria-hidden="true"></i> Akun demo untuk mencoba</span>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <div class="auth-demo-body">
                        <button type="button" class="auth-demo-account" data-demo-email="admin@example.com">
                            <span><i class="bi bi-shield-check" aria-hidden="true"></i> Admin</span>
                            <span>admin@example.com</span>
                        </button>
                        <button type="button" class="auth-demo-account" data-demo-email="ahmad@example.com">
                            <span><i class="bi bi-person-badge" aria-hidden="true"></i> Instruktur</span>
                            <span>ahmad@example.com</span>
                        </button>
                        <button type="button" class="auth-demo-account" data-demo-email="budi.student@example.com">
                            <span><i class="bi bi-person" aria-hidden="true"></i> Peserta</span>
                            <span>budi.student@example.com</span>
                        </button>
                        <p class="auth-demo-note">Password semua akun demo: <strong>elips123</strong></p>
                    </div>
                </details>
                <?php endif; ?>
            </div>

            <a class="auth-back" href="<?= url('index.php') ?>">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke beranda
            </a>
        </main>
    </div>
<?php end_layout(); ?>