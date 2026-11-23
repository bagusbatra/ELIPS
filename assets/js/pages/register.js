// ELIPS LMS - skrip halaman register.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    if (!form) return;

    const formError = document.getElementById('registerError');

    function showError(message, field) {
        if (formError) {
            formError.textContent = message;
            formError.hidden = false;
        } else {
            ELIPS.toast(message);
        }
        if (field && typeof field.focus === 'function') field.focus();
    }

    function clearError() {
        if (!formError) return;
        formError.textContent = '';
        formError.hidden = true;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearError();

        const firstName = document.getElementById('firstName');
        const lastName = document.getElementById('lastName');
        const email = document.getElementById('email');
        const phone = document.getElementById('phone');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('passwordConfirm');

        if (!firstName.value.trim() || !lastName.value.trim() || !email.value.trim() ||
            !phone.value.trim() || !password.value.trim()) {
            showError('Mohon lengkapi semua field.', firstName);
            return;
        }
        if (password.value.length < 8) {
            showError('Password minimal 8 karakter.', password);
            return;
        }
        if (password.value !== confirmPassword.value) {
            showError('Konfirmasi password tidak sesuai.', confirmPassword);
            return;
        }

        const result = ELIPS.createUser({
            name: firstName.value.trim() + ' ' + lastName.value.trim(),
            email: email.value.trim().toLowerCase(),
            phone: phone.value.trim(),
            password: password.value,
            role: 'peserta',
            status: 'aktif'
        });
        if (!result.ok) {
            showError(result.message, email);
            return;
        }

        ELIPS.setCurrentUser(result.data.id);
        ELIPS.toast('Pendaftaran berhasil. Selamat datang!');
        setTimeout(function () {
            window.location.href = 'student/dashboard.php';
        }, 600);
    });

    form.querySelectorAll('input').forEach(function (input) {
        input.addEventListener('input', clearError);
    });

    // Tampilkan/sembunyikan password dan konfirmasi password.
    form.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId);
            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute(
                'aria-label',
                (show ? 'Sembunyikan ' : 'Tampilkan ') +
                (targetId === 'password' ? 'password' : 'konfirmasi password')
            );
            button.innerHTML = '<i class="bi bi-' + (show ? 'eye-slash' : 'eye') + '" aria-hidden="true"></i>';
        });
    });
});
