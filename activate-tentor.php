<?php
// Direktori: activate-tentor.php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'activate-tentor',
    'title' => 'Aktivasi Akun Tentor - ELIPS LMS',
    'body_class' => 'auth-page',
    'css' => ['auth', 'activation', 'tutor'],
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
                <span class="auth-eyebrow">SELAMAT BERGABUNG, TENTOR</span>
                <h1>Lengkapi data, lalu mulai mengajar.</h1>
                <p>Isi biodata, unggah berkas pendukung, dan buat sandi. Setelah itu akunmu aktif dan kamu langsung masuk ke dashboard tentor.</p>
            </div>
            <div class="auth-aside-foot">
                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                <span>Link aktivasi hanya berlaku untukmu dan hanya bisa dipakai sekali.</span>
            </div>
        </aside>

        <main class="auth-main">
            <div class="auth-card auth-card-wide">
                <div class="auth-card-head">
                    <span class="auth-mark" aria-hidden="true"><i class="bi bi-easel"></i></span>
                    <h2>Aktivasi akun tentor</h2>
                    <p id="actIntro">Memeriksa link aktivasi…</p>
                </div>

                <div class="act-state" id="actError" hidden>
                    <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                    <p id="actErrorText"></p>
                    <p><a href="<?= url('login.php') ?>">Ke halaman masuk</a></p>
                </div>

                <div id="actBody" hidden>
                    <ol class="act-steps" aria-label="Langkah aktivasi">
                        <li id="actStep1" class="is-active">1. Data &amp; berkas</li>
                        <li id="actStep2">2. Buat sandi</li>
                    </ol>

                    <form class="auth-form" id="actBioForm" novalidate>
                        <p class="auth-error" id="actBioError" role="alert" hidden></p>
                        <div class="act-grid">
                            <div class="tut-section-title">Biodata</div>
                            <div class="auth-field is-full">
                                <label for="fullName">Nama lengkap</label>
                                <input type="text" id="fullName" class="act-plain" maxlength="150" autocomplete="name" required>
                            </div>
                            <div class="auth-field">
                                <label for="birthPlace">Tempat lahir</label>
                                <input type="text" id="birthPlace" class="act-plain" maxlength="100" required>
                            </div>
                            <div class="auth-field">
                                <label for="birthDate">Tanggal lahir</label>
                                <input type="date" id="birthDate" class="act-plain" required>
                            </div>
                            <div class="auth-field is-full">
                                <label for="address">Alamat lengkap</label>
                                <textarea id="address" class="act-textarea" maxlength="500" autocomplete="street-address" required></textarea>
                            </div>
                            <div class="auth-field">
                                <label for="phone">No. telepon / WhatsApp</label>
                                <input type="tel" id="phone" class="act-plain" maxlength="40" autocomplete="tel" required>
                            </div>
                            <div class="auth-field">
                                <label for="email">Email aktif</label>
                                <input type="email" id="email" class="act-plain" maxlength="150" autocomplete="email" required>
                            </div>

                            <div class="tut-section-title">Berkas (PDF, JPG, atau PNG, maks. 3 MB)</div>
                            <div class="tut-list" id="tutFixedDocs"></div>
                            <div class="tut-list" id="tutCertList"></div>
                            <button type="button" class="tut-add" id="tutAddCert"><i class="bi bi-plus-circle" aria-hidden="true"></i> Tambah sertifikat</button>
                            <p class="tut-hint" id="tutHint">Semua data dan berkas wajib diisi. Minimal satu sertifikat pendukung.</p>
                        </div>
                        <button type="submit" class="auth-submit">Lanjut: buat sandi <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                    </form>

                    <form class="auth-form" id="actPwForm" novalidate hidden>
                        <p class="auth-error" id="actPwError" role="alert" hidden></p>
                        <div class="auth-field">
                            <label for="password">Sandi</label>
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
                            <label for="passwordConfirm">Ulangi sandi</label>
                            <div class="auth-input">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <input type="password" id="passwordConfirm" class="form-input" autocomplete="new-password" required>
                                <button class="auth-toggle-pass" type="button" data-toggle-password="passwordConfirm" aria-label="Tampilkan konfirmasi sandi" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="act-actions">
                            <button type="button" class="act-back" id="actBack"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali</button>
                            <button type="submit" class="auth-submit" id="actSubmit">Aktifkan akun <i class="bi bi-check2-circle" aria-hidden="true"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
<?php end_layout(); ?>