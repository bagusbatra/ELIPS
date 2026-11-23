// Direktori: assets/js/pages/forgot-password.js
// ELIPS LMS - skrip halaman forgot-password.php
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('forgotForm');
    if (!form) return;
    var errorEl = document.getElementById('forgotError');

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        errorEl.hidden = true;
        var identifier = document.getElementById('identifier').value.trim();
        if (!identifier) {
            errorEl.textContent = 'Isi email atau nomor telepon akunmu.';
            errorEl.hidden = false;
            return;
        }
        var result = ELIPS.api.call('password.forgot', { identifier: identifier });
        if (!result.ok) {
            errorEl.textContent = result.message || 'Permintaan gagal dikirim.';
            errorEl.hidden = false;
            return;
        }
        form.hidden = true;
        document.getElementById('forgotDoneText').textContent = result.message;
        document.getElementById('forgotDone').hidden = false;
    });
});