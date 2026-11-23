// Direktori: assets/js/pages/reset-password.js
// ELIPS LMS - skrip halaman reset-password.php
document.addEventListener('DOMContentLoaded', function () {
    var token = new URLSearchParams(window.location.search).get('token') || '';
    var $ = function (id) { return document.getElementById(id); };

    function fail(message) {
        $('resetIntro').textContent = '';
        $('resetForm').hidden = true;
        $('resetError').hidden = false;
        $('resetErrorText').textContent = message;
    }

    if (!token) {
        fail('Link reset tidak lengkap. Gunakan link persis seperti yang dikirim admin.');
        return;
    }
    var info = ELIPS.api.call('password.reset.info', { token: token });
    if (!info.ok) {
        fail(info.message || 'Link reset tidak valid.');
        return;
    }

    $('resetIntro').textContent = 'Halo ' + info.data.name + ', buat sandi baru untuk akunmu.';
    $('resetForm').hidden = false;
    ELIPS.passwordRules.attach($('password'), $('pwRules'));
    ELIPS.passwordRules.bindToggles($('resetForm'));

    $('resetForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var errorEl = $('resetFormError');
        var password = $('password').value;
        var confirm = $('passwordConfirm').value;
        errorEl.hidden = true;
        if (!ELIPS.passwordRules.allValid(password)) {
            errorEl.textContent = 'Sandi belum memenuhi semua aturan.';
            errorEl.hidden = false;
            return;
        }
        if (password !== confirm) {
            errorEl.textContent = 'Konfirmasi sandi tidak sama.';
            errorEl.hidden = false;
            return;
        }
        var result = ELIPS.api.call('password.reset.complete', { token: token, password: password, passwordConfirm: confirm });
        if (!result.ok) {
            errorEl.textContent = result.message || 'Sandi gagal diubah.';
            errorEl.hidden = false;
            return;
        }
        $('resetForm').hidden = true;
        $('resetIntro').textContent = '';
        $('resetDone').hidden = false;
    });
});