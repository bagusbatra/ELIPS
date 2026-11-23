// ELIPS LMS - skrip halaman register.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const firstName = document.getElementById('firstName').value.trim();
        const lastName = document.getElementById('lastName').value.trim();
        const email = document.getElementById('email').value.trim().toLowerCase();
        const phone = document.getElementById('phone').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('passwordConfirm').value;

        if (!firstName || !lastName || !email || !phone || !password) {
            ELIPS.toast('Mohon lengkapi semua field.');
            return;
        }
        if (password.length < 8) {
            ELIPS.toast('Password minimal 8 karakter.');
            return;
        }
        if (password !== confirmPassword) {
            ELIPS.toast('Konfirmasi password tidak sesuai.');
            return;
        }

        const result = ELIPS.createUser({
            name: firstName + ' ' + lastName,
            email: email,
            phone: phone,
            password: password,
            role: 'peserta',
            status: 'aktif'
        });
        if (!result.ok) {
            ELIPS.toast(result.message);
            return;
        }

        ELIPS.setCurrentUser(result.data.id);
        ELIPS.toast('Pendaftaran berhasil. Selamat datang!');
        setTimeout(function () {
            window.location.href = 'student/dashboard.php';
        }, 600);
    });
});

