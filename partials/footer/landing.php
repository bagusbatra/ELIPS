<?php $lpHome = is_current('index.php') ? '' : url('index.php'); ?>
<footer class="lp-footer">
    <div class="lp-container">
        <div class="lp-foot-top">
            <div class="lp-foot-brand">
                <div class="lp-foot-logo">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v6"/></svg>
                    ELIPS LMS
                </div>
                <p>Platform Learning Management System untuk pelatihan dan kursus.</p>
            </div>
            <div class="lp-foot-cols">
                <div class="lp-foot-col">
                    <h4>Navigasi</h4>
                    <a href="<?= url('index.php') ?>">Beranda</a>
                    <a href="<?= $lpHome ?>#pelatihan">Pelatihan</a>
                    <a href="<?= $lpHome ?>#unggulan">Program Unggulan</a>
                    <a href="<?= $lpHome ?>#sertifikat">Cek Sertifikat</a>
                    <a href="<?= $lpHome ?>#profil">Profil</a>
                </div>
                <div class="lp-foot-col">
                    <h4>Akun</h4>
                    <a href="<?= url('login.php') ?>">Masuk</a>
                    <a href="<?= url('register.php') ?>">Daftar</a>
                </div>
                <div class="lp-foot-col" id="kontak">
                    <h4>Kontak</h4>
                    <p><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>[ALAMAT LENGKAP]</p>
                    <p><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>[EMAIL]</p>
                    <p><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>[NOMOR TELEPON]</p>
                </div>
            </div>
        </div>
        <div class="lp-foot-bottom">
            <p>&copy; 2026 ELIPS LMS. All rights reserved.</p>
            <div>
                <a href="<?= $lpHome ?>#privasi">Kebijakan Privasi</a>
                <a href="<?= $lpHome ?>#syarat">Syarat dan Ketentuan</a>
            </div>
        </div>
    </div>
</footer>
