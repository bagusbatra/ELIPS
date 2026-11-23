<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'register',
    'title' => 'Daftar - ELIPS LMS',
    'body_class' => 'auth-page register-page',
    'css' => ['auth'],
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
                <span class="auth-eyebrow">MULAI PERJALANANMU</span>
                <h1>Bangun kemampuan yang dibutuhkan dunia kerja.</h1>
                <p>Daftar sekali, lalu pilih pelatihan yang sesuai dengan tujuan kariermu.</p>
                <ul class="auth-highlights">
                    <li><i class="bi bi-calendar-range" aria-hidden="true"></i>Program 12 bulan yang terarah</li>
                    <li><i class="bi bi-graph-up-arrow" aria-hidden="true"></i>Progres belajar terpantau</li>
                    <li><i class="bi bi-patch-check" aria-hidden="true"></i>Sertifikat yang bisa diverifikasi</li>
                </ul>
            </div>

            <div class="auth-aside-foot">
                <i class="bi bi-quote" aria-hidden="true"></i>
                <span>Satu tempat untuk belajar, berlatih, dan membuktikan kemampuanmu.</span>
            </div>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-card-head">
                    <span class="auth-mark" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
                    <h2>Buat akun baru</h2>
                    <p>Isi data dirimu untuk mulai belajar.</p>
                </div>

                <form class="auth-form" id="registerForm" action="<?= url('login.php') ?>" method="post">
                    <p class="auth-error" id="registerError" role="alert" hidden></p>

                    <div class="auth-row">
                        <div class="auth-field">
                            <label for="firstName">Nama depan</label>
                            <div class="auth-input">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <input type="text" id="firstName" class="form-input" name="firstName"
                                       placeholder="Budi" autocomplete="given-name" required>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="lastName">Nama belakang</label>
                            <div class="auth-input">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <input type="text" id="lastName" class="form-input" name="lastName"
                                       placeholder="Santoso" autocomplete="family-name" required>
                            </div>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="email">Email</label>
                        <div class="auth-input">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <input type="email" id="email" class="form-input" name="email"
                                   placeholder="nama@example.com" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="phone">No. telepon</label>
                        <div class="auth-input">
                            <i class="bi bi-telephone" aria-hidden="true"></i>
                            <input type="tel" id="phone" class="form-input" name="phone"
                                   placeholder="08xxxxxxxxxx" autocomplete="tel" required>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="password">Password</label>
                        <div class="auth-input">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" id="password" class="form-input" name="password"
                                   placeholder="Minimal 8 karakter" autocomplete="new-password" required>
                            <button class="auth-toggle-pass" type="button" data-toggle-password="password"
                                    aria-label="Tampilkan password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="passwordConfirm">Konfirmasi password</label>
                        <div class="auth-input">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" id="passwordConfirm" class="form-input" name="passwordConfirm"
                                   placeholder="Ulangi password" autocomplete="new-password" required>
                            <button class="auth-toggle-pass" type="button" data-toggle-password="passwordConfirm"
                                    aria-label="Tampilkan konfirmasi password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit">
                        Buat akun
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="auth-divider"><span>atau</span></div>
                <p class="auth-signup">Sudah punya akun? <a href="<?= url('login.php') ?>">Masuk disini</a></p>
            </div>

            <a class="auth-back" href="<?= url('index.php') ?>">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke beranda
            </a>
        </main>
    </div>
<?php end_layout(); ?>
